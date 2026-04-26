<?php
declare(strict_types=1);

// ============================================================
// api/puntuaciones.php — Gestión de puntuaciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - CRUD básico con SQL directo
// v1.1 - Migrado a sp_registrar_puntuacion y sp_anular_puntuacion
//      - Aplicadas todas las mejoras del checklist:
//        helpers.php, Content-Type, paginación, validaciones,
//        logError, jsonResponse, Location, Allow, PATCH,
//        SQL_CALC_FOUND_ROWS, X-Total-Count, Cache-Control
//      - Juez solo puede ver/registrar sus propias puntuaciones
//        usando id_juez del JWT
//      - Validación que la competición no esté abierta
//        antes de registrar puntuación
//      - ranking_otorgado validado como entero >= 1
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';
require_once __DIR__ . '/../helpers.php';

$method   = $_SERVER['REQUEST_METHOD'];
$pdo      = getConnection();
$raw_body = in_array($method, ['POST','PUT','PATCH']) ? file_get_contents('php://input') : '';

switch ($method) {

    // GET /api/puntuaciones?id_inscripcion=X → puntuaciones de una inscripción
    // GET /api/puntuaciones?id_competicion=X → todas las puntuaciones de un evento
    // Roles: admin, organizador ven todo
    //        juez solo ve las suyas
    case 'GET':
        $payload = requireAuth();
        handleGet($pdo, $payload);
        break;

    // POST /api/puntuaciones → registrar puntuación via sp_registrar_puntuacion
    // Roles: admin, juez
    case 'POST':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_JUEZ);
        handlePost($pdo, $payload, $raw_body);
        break;

    // PUT/PATCH /api/puntuaciones?id=X → corregir ranking
    // Roles: admin, juez (solo la suya)
    case 'PUT':
    case 'PATCH':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_JUEZ);
        handlePut($pdo, $payload, $raw_body);
        break;

    // DELETE /api/puntuaciones?id=X → anular via sp_anular_puntuacion
    // Recalcula resultados automáticamente
    // Roles: admin
    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleDelete($pdo, $payload);
        break;

    default:
        methodNotAllowed(['GET','POST','PUT','PATCH','DELETE']);
}

// -------------------------------------------------------
// GET
// Juez solo ve sus propias puntuaciones (id_juez del JWT)
// -------------------------------------------------------
function handleGet(PDO $pdo, array $payload): void
{
    $id_inscripcion = validateIntPositive(filter_input(INPUT_GET, 'id_inscripcion', FILTER_VALIDATE_INT));
    $id_competicion = validateIntPositive(filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT));

    if (!$id_inscripcion && !$id_competicion) {
        http_response_code(400);
        jsonResponse(['error' => 'Se requiere id_inscripcion o id_competicion']);
        return;
    }

    $isJuezRole = ($payload['role'] ?? '') === ROLE_JUEZ;
    $id_juez_jwt = $isJuezRole ? ($payload['id_juez'] ?? null) : null;

    header('Cache-Control: no-store, max-age=0');
    $pagination = getPaginationParams(50, 200);

    // GET ?id_inscripcion=X → puntuaciones de una inscripción
    if ($id_inscripcion) {
        $sql = 'SELECT SQL_CALC_FOUND_ROWS
                       p.id_puntuacion, p.id_inscripcion, p.id_juez,
                       j.nombre AS nombre_juez, j.licencia,
                       p.ranking_otorgado
                  FROM puntuacion p
                  JOIN juez j ON j.id_juez = p.id_juez
                 WHERE p.id_inscripcion = ?';
        $params = [$id_inscripcion];

        // Juez solo ve la suya
        if ($id_juez_jwt) {
            $sql .= ' AND p.id_juez = ?';
            $params[] = $id_juez_jwt;
        }

        $sql .= ' ORDER BY j.licencia LIMIT ? OFFSET ?';
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        paginatedResponse($pdo, $stmt->fetchAll(), $pagination);
        return;
    }

    // GET ?id_competicion=X → todas las puntuaciones del evento
    $sql = 'SELECT SQL_CALC_FOUND_ROWS
                   p.id_puntuacion,
                   CONCAT(a.nombre, " ", a.apellido) AS atleta,
                   j.nombre AS juez, j.licencia,
                   p.ranking_otorgado,
                   c.nombre_evento,
                   cat.nombre AS categoria
              FROM puntuacion p
              JOIN inscripcion i   ON i.id_inscripcion = p.id_inscripcion
              JOIN atleta      a   ON a.id_atleta      = i.id_atleta
              JOIN juez        j   ON j.id_juez        = p.id_juez
              JOIN competicion c   ON c.id_competicion = i.id_competicion
              LEFT JOIN categoria cat ON cat.id_categoria = i.id_categoria
             WHERE i.id_competicion = ?';
    $params = [$id_competicion];

    // Juez solo ve las suyas
    if ($id_juez_jwt) {
        $sql .= ' AND p.id_juez = ?';
        $params[] = $id_juez_jwt;
    }

    $sql .= ' ORDER BY a.apellido, j.licencia LIMIT ? OFFSET ?';
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    paginatedResponse($pdo, $stmt->fetchAll(), $pagination);
}

// -------------------------------------------------------
// POST — registrar puntuación via sp_registrar_puntuacion
// -------------------------------------------------------
function handlePost(PDO $pdo, array $payload, string $raw_body): void
{
    $data = validateJsonBody($raw_body);
    if (!$data) {
        http_response_code(400);
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $errors = [];

    // id_inscripcion obligatorio
    $id_inscripcion = validateIntPositive($data['id_inscripcion'] ?? null);
    if (!$id_inscripcion) $errors[] = 'id_inscripcion debe ser un entero positivo';

    // ranking_otorgado obligatorio >= 1
    $ranking = isset($data['ranking_otorgado']) ? (int) $data['ranking_otorgado'] : null;
    if ($ranking === null)  $errors[] = 'ranking_otorgado es requerido';
    if ($ranking !== null && $ranking < 1) $errors[] = 'ranking_otorgado debe ser >= 1';

    // id_juez: si es rol juez usar el del JWT, si es admin puede especificarlo
    $isJuezRole = ($payload['role'] ?? '') === ROLE_JUEZ;
    if ($isJuezRole) {
        $id_juez = $payload['id_juez'] ?? null;
        if (!$id_juez) {
            http_response_code(403);
            jsonResponse(['error' => 'Tu usuario no tiene un juez asociado en la BD']);
            return;
        }
    } else {
        $id_juez = validateIntPositive($data['id_juez'] ?? null);
        if (!$id_juez) $errors[] = 'id_juez debe ser un entero positivo';
    }

    if (!empty($errors)) {
        http_response_code(400);
        jsonResponse(['errors' => $errors]);
        return;
    }

    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare(
            'CALL sp_registrar_puntuacion(:id_inscripcion, :id_juez, :ranking, :ip, @id_puntuacion)'
        );
        $stmt->execute([
            ':id_inscripcion' => $id_inscripcion,
            ':id_juez'        => $id_juez,
            ':ranking'        => $ranking,
            ':ip'             => $ip,
        ]);

        $result = $pdo->query('SELECT @id_puntuacion AS id_puntuacion')->fetch();
        $id_puntuacion = (int) $result['id_puntuacion'];

        // Devolver objeto completo
        $punt = $pdo->prepare(
            'SELECT p.id_puntuacion, p.id_inscripcion, p.id_juez,
                    j.nombre AS nombre_juez, j.licencia, p.ranking_otorgado
               FROM puntuacion p
               JOIN juez j ON j.id_juez = p.id_juez
              WHERE p.id_puntuacion = ?'
        );
        $punt->execute([$id_puntuacion]);

        addLocationHeader('puntuaciones', $id_puntuacion);
        jsonResponse($punt->fetch(), 201);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            jsonResponse(['error' => 'Este juez ya ha puntuado a este atleta en este evento']);
            return;
        }
        logError('puntuaciones POST', $e);
        http_response_code(422);
        jsonResponse(['error' => cleanSpError($e->getMessage())]);
    }
}

// -------------------------------------------------------
// PUT/PATCH — corregir ranking de una puntuación
// Juez solo puede corregir la suya
// -------------------------------------------------------
function handlePut(PDO $pdo, array $payload, string $raw_body): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        http_response_code(400);
        jsonResponse(['error' => 'ID de puntuación requerido']);
        return;
    }

    $data = validateJsonBody($raw_body);
    if (!$data) {
        http_response_code(400);
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $ranking = isset($data['ranking_otorgado']) ? (int) $data['ranking_otorgado'] : null;
    if ($ranking === null || $ranking < 1) {
        http_response_code(400);
        jsonResponse(['error' => 'ranking_otorgado debe ser un entero >= 1']);
        return;
    }

    // Verificar que la puntuación existe y pertenece al juez si es rol juez
    $isJuezRole  = ($payload['role'] ?? '') === ROLE_JUEZ;
    $id_juez_jwt = $isJuezRole ? ($payload['id_juez'] ?? null) : null;

    $check = $pdo->prepare('SELECT id_puntuacion, id_juez FROM puntuacion WHERE id_puntuacion = ?');
    $check->execute([$id]);
    $punt = $check->fetch();

    if (!$punt) {
        http_response_code(404);
        jsonResponse(['error' => 'Puntuación no encontrada']);
        return;
    }

    // Juez solo puede editar la suya
    if ($id_juez_jwt && (int) $punt['id_juez'] !== $id_juez_jwt) {
        http_response_code(403);
        jsonResponse(['error' => 'Solo puedes modificar tus propias puntuaciones']);
        return;
    }

    $stmt = $pdo->prepare('UPDATE puntuacion SET ranking_otorgado = ? WHERE id_puntuacion = ?');
    $stmt->execute([$ranking, $id]);

    jsonResponse(['mensaje' => 'Puntuación actualizada correctamente']);
}

// -------------------------------------------------------
// DELETE — anular puntuación via sp_anular_puntuacion
// Recalcula resultados automáticamente
// -------------------------------------------------------
function handleDelete(PDO $pdo, array $payload): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        http_response_code(400);
        jsonResponse(['error' => 'ID de puntuación requerido']);
        return;
    }

    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare('CALL sp_anular_puntuacion(:id, :ip, @filas)');
        $stmt->execute([':id' => $id, ':ip' => $ip]);

        $result = $pdo->query('SELECT @filas AS filas')->fetch();

        jsonResponse([
            'mensaje'         => 'Puntuación anulada y resultados recalculados',
            'filas_afectadas' => (int) $result['filas'],
        ]);

    } catch (PDOException $e) {
        logError('puntuaciones DELETE', $e);
        http_response_code(422);
        jsonResponse(['error' => cleanSpError($e->getMessage())]);
    }
}
