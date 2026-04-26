<?php
declare(strict_types=1);

// ============================================================
// api/competiciones.php — Gestión de competiciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - CRUD básico con SQL directo
// v1.1 - Aplicadas todas las mejoras del checklist:
//      - helpers.php compartido (validateContentType, sanitize,
//        getClientIp, cleanSpError, jsonResponse, logError,
//        addLocationHeader, methodNotAllowed, paginatedResponse)
//      - Content-Type validado en POST/PUT/PATCH
//      - php://input leído una sola vez
//      - Paginación con SQL_CALC_FOUND_ROWS
//      - X-Total-Count en header
//      - Cache-Control en GET
//      - Location header en 201
//      - Allow header en 405
//      - Todos los errores de validación recogidos juntos
//      - rowCount() en lugar de query extra
//      - Strings sanitizados y validados
//      - json_encode seguro via jsonResponse()
//      - logError() con stack trace en errores 5xx
//      - PATCH además de PUT
//      - fn_estado_competicion para mostrar estado calculado
//      - Filtros de búsqueda: ?estado=abierta|en_curso|cerrada
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';

$method   = $_SERVER['REQUEST_METHOD'];
$pdo      = getConnection();
$raw_body = in_array($method, ['POST','PUT','PATCH']) ? file_get_contents('php://input') : '';

switch ($method) {

    // GET /api/competiciones          → lista paginada
    // GET /api/competiciones?id=X     → detalle con estado calculado
    // GET /api/competiciones?estado=X → filtrar por estado
    // Accesible para todos los roles
    case 'GET':
        handleCompGet($pdo);
        break;

    // POST /api/competiciones → crear evento
    // Roles: admin, organizador
    case 'POST':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_ORGANIZADOR);
        handleCompPost($pdo, $raw_body);
        break;

    // PUT/PATCH /api/competiciones?id=X → actualizar evento
    // Roles: admin, organizador
    case 'PUT':
    case 'PATCH':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_ORGANIZADOR);
        handleCompPut($pdo, $raw_body);
        break;

    // DELETE /api/competiciones?id=X → eliminar evento
    // Solo si no tiene inscripciones (ON DELETE CASCADE lo gestiona la BD)
    // Roles: admin
    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleCompDelete($pdo);
        break;

    default:
        methodNotAllowed(['GET','POST','PUT','PATCH','DELETE']);
}

// -------------------------------------------------------
// GET
// -------------------------------------------------------
function handleCompGet(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));

    // GET ?id=X → detalle con estado calculado via fn_estado_competicion
    if ($id) {
        $stmt = $pdo->prepare(
            'SELECT c.id_competicion, c.nombre_evento, c.fecha, c.lugar,
                    fn_estado_competicion(c.id_competicion) AS estado,
                    COUNT(i.id_inscripcion) AS total_inscritos
               FROM competicion c
               LEFT JOIN inscripcion i ON i.id_competicion = c.id_competicion
              WHERE c.id_competicion = ?
              GROUP BY c.id_competicion'
        );
        $stmt->execute([$id]);
        $comp = $stmt->fetch();

        if (!$comp) {
            http_response_code(404);
            jsonResponse(['error' => 'Competición no encontrada']);
            return;
        }

        header('Cache-Control: no-store, max-age=0');
        jsonResponse($comp);
        return;
    }

    // Filtro por estado calculado
    $estado_filtro = filter_input(INPUT_GET, 'estado', FILTER_SANITIZE_SPECIAL_CHARS);
    $estados_validos = ['abierta', 'en_curso', 'cerrada', 'sin_fecha'];
    if ($estado_filtro && !in_array($estado_filtro, $estados_validos, true)) {
        http_response_code(400);
        jsonResponse(['error' => 'estado debe ser: abierta, en_curso, cerrada o sin_fecha']);
        return;
    }

    $pagination = getPaginationParams(20, 100);

    // Filtrar por estado si se indica
    if ($estado_filtro) {
        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    c.id_competicion, c.nombre_evento, c.fecha, c.lugar,
                    fn_estado_competicion(c.id_competicion) AS estado,
                    COUNT(i.id_inscripcion) AS total_inscritos
               FROM competicion c
               LEFT JOIN inscripcion i ON i.id_competicion = c.id_competicion
              GROUP BY c.id_competicion
             HAVING estado = ?
              ORDER BY c.fecha DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$estado_filtro, $pagination['limit'], $pagination['offset']]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    c.id_competicion, c.nombre_evento, c.fecha, c.lugar,
                    fn_estado_competicion(c.id_competicion) AS estado,
                    COUNT(i.id_inscripcion) AS total_inscritos
               FROM competicion c
               LEFT JOIN inscripcion i ON i.id_competicion = c.id_competicion
              GROUP BY c.id_competicion
              ORDER BY c.fecha DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$pagination['limit'], $pagination['offset']]);
    }

    paginatedResponse($pdo, $stmt->fetchAll(), $pagination);
}

// -------------------------------------------------------
// POST — crear competición
// -------------------------------------------------------
function handleCompPost(PDO $pdo, string $raw_body): void
{
    $data = validateJsonBody($raw_body);
    if (!$data) {
        http_response_code(400);
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $errors = [];

    // nombre_evento obligatorio
    $nombre = trim($data['nombre_evento'] ?? '');
    if ($nombre === '')          $errors[] = 'nombre_evento es requerido';
    if (strlen($nombre) > 200)   $errors[] = 'nombre_evento no puede superar 200 caracteres';

    // fecha opcional pero si se envía debe ser válida
    $fecha = null;
    if (!empty($data['fecha'])) {
        $fechaObj = DateTime::createFromFormat('Y-m-d', $data['fecha']);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $data['fecha']) {
            $errors[] = 'fecha debe tener formato YYYY-MM-DD y ser válida';
        } else {
            $fecha = $data['fecha'];
        }
    }

    // lugar opcional
    $lugar = trim($data['lugar'] ?? '');
    if (strlen($lugar) > 200) $errors[] = 'lugar no puede superar 200 caracteres';
    if ($lugar === '') $lugar = null;

    if (!empty($errors)) {
        http_response_code(400);
        jsonResponse(['errors' => $errors]);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO competicion (nombre_evento, fecha, lugar)
             VALUES (:nombre_evento, :fecha, :lugar)'
        );
        $stmt->execute([
            ':nombre_evento' => sanitize($nombre),
            ':fecha'         => $fecha,
            ':lugar'         => $lugar ? sanitize($lugar) : null,
        ]);

        $id = (int) $pdo->lastInsertId();

        // Devolver objeto completo con estado calculado
        $comp = $pdo->prepare(
            'SELECT id_competicion, nombre_evento, fecha, lugar,
                    fn_estado_competicion(id_competicion) AS estado
               FROM competicion WHERE id_competicion = ?'
        );
        $comp->execute([$id]);

        // Header Location estándar REST
        addLocationHeader('competiciones', $id);
        jsonResponse($comp->fetch(), 201);

    } catch (PDOException $e) {
        // Duplicado (mismo nombre + fecha)
        if ($e->getCode() === '23000') {
            http_response_code(409);
            jsonResponse(['error' => 'Ya existe una competición con ese nombre y fecha']);
            return;
        }
        logError('competiciones POST', $e);
        http_response_code(500);
        jsonResponse(['error' => 'Error interno del servidor']);
    }
}

// -------------------------------------------------------
// PUT/PATCH — actualizar competición
// -------------------------------------------------------
function handleCompPut(PDO $pdo, string $raw_body): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        http_response_code(400);
        jsonResponse(['error' => 'ID de competición requerido']);
        return;
    }

    $data = validateJsonBody($raw_body);
    if (!$data) {
        http_response_code(400);
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $errors = [];

    if (isset($data['nombre_evento'])) {
        $nombre = trim($data['nombre_evento']);
        if ($nombre === '')        $errors[] = 'nombre_evento no puede estar vacío';
        if (strlen($nombre) > 200) $errors[] = 'nombre_evento no puede superar 200 caracteres';
    }

    if (!empty($data['fecha'])) {
        $fechaObj = DateTime::createFromFormat('Y-m-d', $data['fecha']);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $data['fecha']) {
            $errors[] = 'fecha debe tener formato YYYY-MM-DD y ser válida';
        }
    }

    if (isset($data['lugar']) && strlen(trim($data['lugar'])) > 200) {
        $errors[] = 'lugar no puede superar 200 caracteres';
    }

    if (!empty($errors)) {
        http_response_code(400);
        jsonResponse(['errors' => $errors]);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE competicion SET
                nombre_evento = CASE WHEN :nombre IS NOT NULL AND :nombre != \'\' THEN :nombre ELSE nombre_evento END,
                fecha         = COALESCE(:fecha,  fecha),
                lugar         = COALESCE(:lugar,  lugar)
              WHERE id_competicion = :id'
        );
        $stmt->execute([
            ':nombre' => isset($data['nombre_evento']) ? sanitize(trim($data['nombre_evento'])) : null,
            ':fecha'  => $data['fecha']  ?? null,
            ':lugar'  => isset($data['lugar']) ? sanitize(trim($data['lugar'])) : null,
            ':id'     => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            jsonResponse(['error' => 'Competición no encontrada o sin cambios']);
            return;
        }

        jsonResponse(['mensaje' => 'Competición actualizada correctamente']);

    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            jsonResponse(['error' => 'Ya existe una competición con ese nombre y fecha']);
            return;
        }
        logError('competiciones PUT', $e);
        http_response_code(500);
        jsonResponse(['error' => 'Error interno del servidor']);
    }
}

// -------------------------------------------------------
// DELETE — eliminar competición
// La BD tiene ON DELETE CASCADE en inscripcion y resultado_final
// Solo admin puede eliminar — acción irreversible
// -------------------------------------------------------
function handleCompDelete(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        http_response_code(400);
        jsonResponse(['error' => 'ID de competición requerido']);
        return;
    }

    // Verificar que no tenga puntuaciones registradas
    // No tiene sentido borrar un evento con resultados
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM puntuacion p
           JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
          WHERE i.id_competicion = ?'
    );
    $check->execute([$id]);
    if ((int) $check->fetchColumn() > 0) {
        http_response_code(409);
        jsonResponse(['error' => 'No se puede eliminar una competición que ya tiene puntuaciones registradas']);
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM competicion WHERE id_competicion = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            jsonResponse(['error' => 'Competición no encontrada']);
            return;
        }

        jsonResponse(['mensaje' => 'Competición eliminada correctamente']);

    } catch (PDOException $e) {
        logError('competiciones DELETE', $e);
        http_response_code(500);
        jsonResponse(['error' => 'Error interno del servidor']);
    }
}
