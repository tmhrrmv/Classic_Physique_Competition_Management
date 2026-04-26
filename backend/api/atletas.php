<?php
declare(strict_types=1);

// ============================================================
// api/atletas.php — Gestión de atletas
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - CRUD básico SQL directo
// v1.1 - sp_inscribir_atleta, DELETE desactiva
// v1.2 - IP real, cleanSpError, paginación,
//        filter_input null/false, fecha pasada,
//        longitud nombre/apellido
// v1.3 - 34 mejoras: TRUSTED_PROXIES, todos errores juntos,
//        SQL_CALC_FOUND_ROWS, PATCH, reactivar, objeto completo,
//        php://input una vez, Content-Type, Cache-Control,
//        X-Total-Count, rowCount(), sanitize, htmlspecialchars
// v1.4 - Migrado a helpers.php: eliminadas funciones
//        atleta_* duplicadas, usa funciones compartidas
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
    case 'GET':
        header('Cache-Control: no-store, max-age=0');
        handleAtletaGet($pdo);
        break;
    case 'POST':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_ORGANIZADOR);
        handleAtletaPost($pdo, $raw_body);
        break;
    case 'PUT':
    case 'PATCH':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleAtletaPut($pdo, $raw_body);
        break;
    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        $reactivar = filter_input(INPUT_GET, 'reactivar', FILTER_VALIDATE_INT);
        $reactivar ? handleAtletaReactivar($pdo) : handleAtletaDelete($pdo);
        break;
    default:
        methodNotAllowed(['GET','POST','PUT','PATCH','DELETE']);
}

// -------------------------------------------------------
// GET
// -------------------------------------------------------
function handleAtletaGet(PDO $pdo): void
{
    $id        = validateIntPositive(filter_input(INPUT_GET, 'id',        FILTER_VALIDATE_INT));
    $historial = validateIntPositive(filter_input(INPUT_GET, 'historial', FILTER_VALIDATE_INT));

    if ($id && $historial) {
        $check = $pdo->prepare('SELECT id_atleta FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonResponse(['error' => 'Atleta no encontrado'], 404);
            return;
        }
        $p = getPaginationParams(10, 50);
        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    a.nombre, a.apellido,
                    c.nombre_evento, c.fecha AS fecha_evento, c.lugar,
                    cat.nombre AS categoria,
                    i.numero_dorsal, i.peso_registro, i.estatura_registro,
                    i.fecha_inscripcion,
                    rf.ranking_final, rf.media_ranking, rf.num_jueces
               FROM inscripcion i
               JOIN atleta        a   ON a.id_atleta      = i.id_atleta
               JOIN competicion   c   ON c.id_competicion = i.id_competicion
               LEFT JOIN categoria    cat ON cat.id_categoria   = i.id_categoria
               LEFT JOIN resultado_final rf ON rf.id_inscripcion = i.id_inscripcion
              WHERE i.id_atleta = ?
              ORDER BY c.fecha DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$id, $p['limit'], $p['offset']]);
        paginatedResponse($pdo, $stmt->fetchAll(), $p);
        return;
    }

    if ($id) {
        $stmt = $pdo->prepare(
            'SELECT id_atleta, nombre, apellido, fecha_nacimiento,
                    nacionalidad, activo, fecha_modificacion
               FROM atleta WHERE id_atleta = ?'
        );
        $stmt->execute([$id]);
        $atleta = $stmt->fetch();
        if (!$atleta) {
            jsonResponse(['error' => 'Atleta no encontrado'], 404);
            return;
        }
        jsonResponse($atleta);
        return;
    }

    $p      = getPaginationParams(20, 100);
    $activo = (filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT) === 0) ? 0 : 1;

    $stmt = $pdo->prepare(
        'SELECT SQL_CALC_FOUND_ROWS
                id_atleta, nombre, apellido, fecha_nacimiento, nacionalidad, activo
           FROM atleta WHERE activo = ?
           ORDER BY apellido, nombre
           LIMIT ? OFFSET ?'
    );
    $stmt->execute([$activo, $p['limit'], $p['offset']]);
    paginatedResponse($pdo, $stmt->fetchAll(), $p);
}

// -------------------------------------------------------
// POST
// -------------------------------------------------------
function handleAtletaPost(PDO $pdo, string $raw_body): void
{
    $data = validateJsonBody($raw_body);
    if (!$data) {
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido'], 400);
        return;
    }

    $errors = [];

    $nombre   = trim($data['nombre']   ?? '');
    $apellido = trim($data['apellido'] ?? '');
    if ($nombre   === '')        $errors[] = 'nombre es requerido';
    if ($apellido === '')        $errors[] = 'apellido es requerido';
    if (strlen($nombre)   > 100) $errors[] = 'nombre no puede superar 100 caracteres';
    if (strlen($apellido) > 100) $errors[] = 'apellido no puede superar 100 caracteres';

    $fn = $data['fecha_nacimiento'] ?? '';
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fn);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fn) {
        $errors[] = 'fecha_nacimiento debe tener formato YYYY-MM-DD válido';
    } elseif ($fechaObj >= new DateTime('today')) {
        $errors[] = 'fecha_nacimiento debe ser una fecha pasada';
    }

    $id_competicion = validateIntPositive($data['id_competicion'] ?? null);
    if (!$id_competicion) $errors[] = 'id_competicion debe ser un entero positivo';

    $nac = strtoupper(trim($data['nacionalidad'] ?? ''));
    if ($nac === '') $nac = null;
    if ($nac !== null && !preg_match('/^[A-Z]{3}$/', $nac)) {
        $errors[] = 'nacionalidad debe ser código ISO alpha-3 (ESP, ARG...)';
    }

    $peso     = isset($data['peso_registro'])     ? (float) $data['peso_registro']     : null;
    $estatura = isset($data['estatura_registro']) ? (float) $data['estatura_registro'] : null;
    if ($peso     !== null && $peso     <= 0) $errors[] = 'peso_registro debe ser positivo';
    if ($estatura !== null && $estatura <= 0) $errors[] = 'estatura_registro debe ser positivo';

    $dorsal = isset($data['numero_dorsal']) ? (int) $data['numero_dorsal'] : null;
    if ($dorsal !== null && $dorsal < 1) $errors[] = 'numero_dorsal debe ser >= 1';

    $id_categoria = isset($data['id_categoria']) ? (int) $data['id_categoria'] : null;
    if ($id_categoria !== null) {
        $catCheck = $pdo->prepare('SELECT id_categoria FROM categoria WHERE id_categoria = ?');
        $catCheck->execute([$id_categoria]);
        if (!$catCheck->fetch()) $errors[] = 'id_categoria no existe';
    }

    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
        return;
    }

    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare(
            'CALL sp_inscribir_atleta(
                :nombre, :apellido, :fecha_nacimiento, :nac,
                :id_competicion, :id_categoria, :dorsal,
                :peso, :estatura, :ip,
                @id_atleta, @id_inscripcion
            )'
        );
        $stmt->execute([
            ':nombre'         => sanitize($nombre),
            ':apellido'       => sanitize($apellido),
            ':fecha_nacimiento'=> $fn,
            ':nac'            => $nac,
            ':id_competicion' => $id_competicion,
            ':id_categoria'   => $id_categoria,
            ':dorsal'         => $dorsal,
            ':peso'           => $peso,
            ':estatura'       => $estatura,
            ':ip'             => $ip,
        ]);

        $result = $pdo->query('SELECT @id_atleta AS id_atleta, @id_inscripcion AS id_inscripcion')->fetch();

        $atletaStmt = $pdo->prepare(
            'SELECT id_atleta, nombre, apellido, fecha_nacimiento, nacionalidad, activo
               FROM atleta WHERE id_atleta = ?'
        );
        $atletaStmt->execute([$result['id_atleta']]);

        addLocationHeader('atletas', (int) $result['id_atleta']);
        jsonResponse([
            'atleta'         => $atletaStmt->fetch(),
            'id_inscripcion' => (int) $result['id_inscripcion'],
            'mensaje'        => 'Atleta inscrito correctamente',
        ], 201);

    } catch (\PDOException $e) {
        logError('atletas POST', $e, false);
        jsonResponse(['error' => cleanSpError($e->getMessage())], 422);
    }
}

// -------------------------------------------------------
// PUT / PATCH
// -------------------------------------------------------
function handleAtletaPut(PDO $pdo, string $raw_body): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID de atleta requerido'], 400);
        return;
    }

    $data = validateJsonBody($raw_body);
    if (!$data) {
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido'], 400);
        return;
    }

    $errors = [];
    if (isset($data['nombre'])) {
        $n = trim($data['nombre']);
        if ($n === '')        $errors[] = 'nombre no puede estar vacío';
        if (strlen($n) > 100) $errors[] = 'nombre no puede superar 100 caracteres';
    }
    if (isset($data['apellido'])) {
        $a = trim($data['apellido']);
        if ($a === '')        $errors[] = 'apellido no puede estar vacío';
        if (strlen($a) > 100) $errors[] = 'apellido no puede superar 100 caracteres';
    }
    $nac = null;
    if (isset($data['nacionalidad'])) {
        $nac = strtoupper(trim($data['nacionalidad']));
        if ($nac === '') $nac = null;
        elseif (!preg_match('/^[A-Z]{3}$/', $nac)) $errors[] = 'nacionalidad debe ser ISO alpha-3';
    }

    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], 400);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE atleta SET
            nombre       = CASE WHEN :nombre   IS NOT NULL AND :nombre   != \'\' THEN :nombre   ELSE nombre       END,
            apellido     = CASE WHEN :apellido IS NOT NULL AND :apellido != \'\' THEN :apellido ELSE apellido     END,
            nacionalidad = CASE WHEN :nac      IS NOT NULL                       THEN :nac      ELSE nacionalidad END
          WHERE id_atleta = :id'
    );
    $stmt->execute([
        ':nombre'   => isset($data['nombre'])       ? sanitize(trim($data['nombre']))   : null,
        ':apellido' => isset($data['apellido'])     ? sanitize(trim($data['apellido'])) : null,
        ':nac'      => $nac,
        ':id'       => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Atleta no encontrado o sin cambios'], 404);
        return;
    }

    jsonResponse(['mensaje' => 'Atleta actualizado correctamente']);
}

// -------------------------------------------------------
// DELETE — desactivar
// -------------------------------------------------------
function handleAtletaDelete(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID de atleta requerido'], 400);
        return;
    }

    $stmt = $pdo->prepare('UPDATE atleta SET activo = 0 WHERE id_atleta = ? AND activo = 1');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT activo FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        $row = $check->fetch();
        $row
            ? jsonResponse(['error' => 'El atleta ya está desactivado'], 409)
            : jsonResponse(['error' => 'Atleta no encontrado'], 404);
        return;
    }

    jsonResponse(['mensaje' => 'Atleta desactivado correctamente']);
}

// -------------------------------------------------------
// Reactivar — DELETE ?reactivar=1
// -------------------------------------------------------
function handleAtletaReactivar(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID de atleta requerido'], 400);
        return;
    }

    $stmt = $pdo->prepare('UPDATE atleta SET activo = 1 WHERE id_atleta = ? AND activo = 0');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT activo FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        $row = $check->fetch();
        $row
            ? jsonResponse(['error' => 'El atleta ya está activo'], 409)
            : jsonResponse(['error' => 'Atleta no encontrado'], 404);
        return;
    }

    jsonResponse(['mensaje' => 'Atleta reactivado correctamente']);
}
