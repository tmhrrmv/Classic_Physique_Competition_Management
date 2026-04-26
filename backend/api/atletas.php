<?php
declare(strict_types=1);

// ============================================================
// api/atletas.php — Gestión de atletas
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - CRUD básico con SQL directo
// v1.1 - Migrado a sp_inscribir_atleta, DELETE desactiva
// v1.2 - IP real con X-Forwarded-For, cleanSpError,
//        paginación, distinción null/false filter_input,
//        fecha_nacimiento validada como pasada,
//        longitud máxima nombre y apellido
// v1.3 - Mejora 1:  validación IP con TRUSTED_PROXIES
//      - Mejora 2:  cleanSpError mejorado con fallback
//      - Mejora 3:  id_competicion validado como entero positivo
//      - Mejora 4:  historial con paginación
//      - Mejora 5:  rowCount() en PUT/DELETE sin query extra
//      - Mejora 6:  TRUSTED_PROXIES en config.php
//      - Mejora 7:  htmlspecialchars en strings antes de guardar
//      - Mejora 8:  COALESCE no permite strings vacíos
//      - Mejora 9:  page y limit validados como enteros positivos
//      - Mejora 10: historial devuelve 404 si atleta no existe
//      - Mejora 11: helpers movidos a funciones con prefijo atleta_
//      - Mejora 12: POST devuelve todos los errores de validación
//      - Mejora 13: SQL_CALC_FOUND_ROWS para total en una query
//      - Mejora 15: peso y estatura validados como positivos
//      - Mejora 16: numero_dorsal validado como entero >= 1
//      - Mejora 17: id_categoria verificado en BD antes del sp_
//      - Mejora 18: PATCH para actualización parcial estándar REST
//      - Mejora 19: endpoint para reactivar atleta (activo=1)
//      - Mejora 20: POST devuelve objeto completo del atleta
//      - Mejora 21: php://input leído una sola vez al inicio
//      - Mejora 23: rate limiting básico por IP
//      - Mejora 25: Content-Type validado en POST/PUT/PATCH
//      - Mejora 26: logging de errores internos con error_log
//      - Mejora 29: Cache-Control en respuestas GET
//      - Mejora 30: X-Total-Count en header de paginación
//      - Mejora 32: nacionalidad vacía convertida a null
//      - Mejora 33: trim vacío validado en nombre y apellido
//      - Mejora 34: fecha_nacimiento validada como fecha real
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();

// Mejora 21: leer php://input una sola vez
$raw_body = in_array($method, ['POST','PUT','PATCH']) ? file_get_contents('php://input') : '';

switch ($method) {
    case 'GET':
        header('Cache-Control: no-store, max-age=0'); // mejora 29
        handleGet($pdo);
        break;

    case 'POST':
        atleta_validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_ORGANIZADOR);
        handlePost($pdo, $payload, $raw_body);
        break;

    case 'PUT':
    case 'PATCH': // mejora 18: PATCH para actualización parcial
        atleta_validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handlePut($pdo, $raw_body);
        break;

    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        // mejora 19: ?reactivar=1 para reactivar atleta
        $reactivar = filter_input(INPUT_GET, 'reactivar', FILTER_VALIDATE_INT);
        if ($reactivar) {
            handleReactivar($pdo);
        } else {
            handleDelete($pdo);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}

// -------------------------------------------------------
// Mejora 25: validar Content-Type en POST/PUT/PATCH
// -------------------------------------------------------
function atleta_validateContentType(): void
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') === false) {
        http_response_code(415);
        echo json_encode(['error' => 'Content-Type debe ser application/json']);
        exit;
    }
}

// -------------------------------------------------------
// Mejora 1 + 6: IP real validando TRUSTED_PROXIES
// Solo se usa X-Forwarded-For si viene de proxy de confianza
// -------------------------------------------------------
function atleta_getClientIp(): string
{
    $remoteIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if ($forwarded !== '' && in_array($remoteIp, TRUSTED_PROXIES, true)) {
        $ips = explode(',', $forwarded);
        $ip  = trim($ips[0]);
        // Mejora 1: validar que sea IP válida
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $remoteIp;
}

// -------------------------------------------------------
// Mejora 2: cleanSpError mejorado con fallback
// -------------------------------------------------------
function atleta_cleanSpError(string $message): string
{
    if (preg_match('/:\s*(.+)$/', $message, $matches)) {
        $clean = trim($matches[1]);
        return $clean !== '' ? $clean : 'Error al procesar la operación';
    }
    return 'Error al procesar la operación';
}

// -------------------------------------------------------
// Mejora 7: sanear string contra XSS
// -------------------------------------------------------
function atleta_sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------
// GET
// Mejora 4:  historial con paginación
// Mejora 9:  page y limit validados
// Mejora 10: historial devuelve 404 si atleta no existe
// Mejora 13: SQL_CALC_FOUND_ROWS para total en una query
// Mejora 30: X-Total-Count en header
// -------------------------------------------------------
function handleGet(PDO $pdo): void
{
    $id_raw    = filter_input(INPUT_GET, 'id',        FILTER_VALIDATE_INT);
    $historial = filter_input(INPUT_GET, 'historial', FILTER_VALIDATE_INT);
    $id = ($id_raw !== null && $id_raw !== false && $id_raw > 0) ? $id_raw : null;

    // GET ?id=X&historial=1 → historial paginado del atleta
    if ($id && $historial) {

        // Mejora 10: verificar que el atleta existe
        $check = $pdo->prepare('SELECT id_atleta FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Atleta no encontrado']);
            return;
        }

        // Mejora 4: paginación en historial
        $page   = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
        $limit  = min(50, max(1, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10)));
        $offset = ($page - 1) * $limit;

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
        $stmt->execute([$id, $limit, $offset]);
        $data  = $stmt->fetchAll();
        $total = (int) $pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

        // Mejora 30: total en header
        header('X-Total-Count: ' . $total);
        echo json_encode([
            'data'       => $data,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
        return;
    }

    // GET ?id=X → detalle
    if ($id) {
        $stmt = $pdo->prepare(
            'SELECT id_atleta, nombre, apellido, fecha_nacimiento,
                    nacionalidad, activo, fecha_modificacion
               FROM atleta WHERE id_atleta = ?'
        );
        $stmt->execute([$id]);
        $atleta = $stmt->fetch();

        if (!$atleta) {
            http_response_code(404);
            echo json_encode(['error' => 'Atleta no encontrado']);
            return;
        }

        echo json_encode($atleta);
        return;
    }

    // Mejora 9: validar page y limit
    $page  = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
    $limit = min(100, max(1, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20)));
    $offset = ($page - 1) * $limit;

    $activo_raw = filter_input(INPUT_GET, 'activo', FILTER_VALIDATE_INT);
    $activo = ($activo_raw === 0) ? 0 : 1;

    // Mejora 13: SQL_CALC_FOUND_ROWS — total en una sola query
    $stmt = $pdo->prepare(
        'SELECT SQL_CALC_FOUND_ROWS
                id_atleta, nombre, apellido, fecha_nacimiento, nacionalidad, activo
           FROM atleta
          WHERE activo = ?
          ORDER BY apellido, nombre
          LIMIT ? OFFSET ?'
    );
    $stmt->execute([$activo, $limit, $offset]);
    $data  = $stmt->fetchAll();
    $total = (int) $pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

    // Mejora 30: total en header
    header('X-Total-Count: ' . $total);
    echo json_encode([
        'data'       => $data,
        'pagination' => [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);
}

// -------------------------------------------------------
// POST — inscribir atleta
// Mejora 12: devolver todos los errores de validación
// Mejora 20: devolver objeto completo del atleta
// -------------------------------------------------------
function handlePost(PDO $pdo, array $payload, string $raw_body): void
{
    $data = json_decode($raw_body, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    // Mejora 12: recoger todos los errores antes de responder
    $errors = [];

    $required = ['nombre', 'apellido', 'fecha_nacimiento', 'id_competicion'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Campo requerido: $field";
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        return;
    }

    // Mejora 33: trim vacío en nombre y apellido
    $nombre   = trim($data['nombre']);
    $apellido = trim($data['apellido']);
    if ($nombre === '')   $errors[] = 'nombre no puede estar vacío';
    if ($apellido === '') $errors[] = 'apellido no puede estar vacío';

    // Mejora 6: longitud máxima
    if (strlen($nombre)   > 100) $errors[] = 'nombre no puede superar 100 caracteres';
    if (strlen($apellido) > 100) $errors[] = 'apellido no puede superar 100 caracteres';

    // Mejora 34: fecha real válida
    $fecha = DateTime::createFromFormat('Y-m-d', $data['fecha_nacimiento'] ?? '');
    if (!$fecha || $fecha->format('Y-m-d') !== $data['fecha_nacimiento']) {
        $errors[] = 'fecha_nacimiento debe tener formato YYYY-MM-DD y ser válida';
    } elseif ($fecha >= new DateTime('today')) {
        // Mejora 5 (v1.2): fecha debe ser pasada
        $errors[] = 'fecha_nacimiento debe ser una fecha pasada';
    }

    // Mejora 3: id_competicion como entero positivo
    $id_competicion = filter_var($data['id_competicion'] ?? null, FILTER_VALIDATE_INT);
    if (!$id_competicion || $id_competicion <= 0) {
        $errors[] = 'id_competicion debe ser un entero positivo';
    }

    // Mejora 32: nacionalidad vacía → null
    $nacionalidad = strtoupper(trim($data['nacionalidad'] ?? ''));
    if ($nacionalidad === '') $nacionalidad = null;
    if ($nacionalidad !== null && !preg_match('/^[A-Z]{3}$/', $nacionalidad)) {
        $errors[] = 'nacionalidad debe ser código ISO alpha-3 (ESP, ARG...)';
    }

    // Mejora 15: peso y estatura positivos
    $peso     = isset($data['peso_registro'])     ? (float) $data['peso_registro']     : null;
    $estatura = isset($data['estatura_registro']) ? (float) $data['estatura_registro'] : null;
    if ($peso     !== null && $peso     <= 0) $errors[] = 'peso_registro debe ser positivo';
    if ($estatura !== null && $estatura <= 0) $errors[] = 'estatura_registro debe ser positivo';

    // Mejora 16: numero_dorsal >= 1
    $dorsal = isset($data['numero_dorsal']) ? (int) $data['numero_dorsal'] : null;
    if ($dorsal !== null && $dorsal < 1) $errors[] = 'numero_dorsal debe ser >= 1';

    // Mejora 17: verificar id_categoria en BD
    $id_categoria = isset($data['id_categoria']) ? (int) $data['id_categoria'] : null;
    if ($id_categoria !== null) {
        $catCheck = $pdo->prepare('SELECT id_categoria FROM categoria WHERE id_categoria = ?');
        $catCheck->execute([$id_categoria]);
        if (!$catCheck->fetch()) $errors[] = 'id_categoria no existe';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        return;
    }

    $ip = atleta_getClientIp();

    try {
        $stmt = $pdo->prepare(
            'CALL sp_inscribir_atleta(
                :nombre, :apellido, :fecha_nacimiento, :nacionalidad,
                :id_competicion, :id_categoria, :numero_dorsal,
                :peso_registro, :estatura_registro, :ip,
                @id_atleta, @id_inscripcion
            )'
        );
        $stmt->execute([
            ':nombre'            => atleta_sanitize($nombre),
            ':apellido'          => atleta_sanitize($apellido),
            ':fecha_nacimiento'  => $data['fecha_nacimiento'],
            ':nacionalidad'      => $nacionalidad,
            ':id_competicion'    => $id_competicion,
            ':id_categoria'      => $id_categoria,
            ':numero_dorsal'     => $dorsal,
            ':peso_registro'     => $peso,
            ':estatura_registro' => $estatura,
            ':ip'                => $ip,
        ]);

        $result = $pdo->query('SELECT @id_atleta AS id_atleta, @id_inscripcion AS id_inscripcion')->fetch();

        // Mejora 20: devolver objeto completo del atleta
        $atletaStmt = $pdo->prepare(
            'SELECT id_atleta, nombre, apellido, fecha_nacimiento, nacionalidad, activo
               FROM atleta WHERE id_atleta = ?'
        );
        $atletaStmt->execute([$result['id_atleta']]);
        $atletaData = $atletaStmt->fetch();

        http_response_code(201);
        echo json_encode([
            'atleta'         => $atletaData,
            'id_inscripcion' => (int) $result['id_inscripcion'],
            'mensaje'        => 'Atleta inscrito correctamente',
        ]);

    } catch (PDOException $e) {
        // Mejora 26: log interno del error real
        error_log('[' . REQUEST_ID . '] atletas POST error: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['error' => atleta_cleanSpError($e->getMessage())]);
    }
}

// -------------------------------------------------------
// PUT/PATCH — actualizar datos de identidad
// Mejora 5:  rowCount() en lugar de query extra
// Mejora 8:  no permitir strings vacíos con COALESCE
// -------------------------------------------------------
function handlePut(PDO $pdo, string $raw_body): void
{
    $id_raw = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $id = ($id_raw !== null && $id_raw !== false && $id_raw > 0) ? $id_raw : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de atleta requerido']);
        return;
    }

    $data = json_decode($raw_body, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $errors = [];

    // Mejora 33: trim vacío
    if (isset($data['nombre'])) {
        $nombre = trim($data['nombre']);
        if ($nombre === '')        $errors[] = 'nombre no puede estar vacío';
        if (strlen($nombre) > 100) $errors[] = 'nombre no puede superar 100 caracteres';
    }
    if (isset($data['apellido'])) {
        $apellido = trim($data['apellido']);
        if ($apellido === '')        $errors[] = 'apellido no puede estar vacío';
        if (strlen($apellido) > 100) $errors[] = 'apellido no puede superar 100 caracteres';
    }

    // Mejora 32: nacionalidad vacía → null
    if (isset($data['nacionalidad'])) {
        $nacionalidad = strtoupper(trim($data['nacionalidad']));
        if ($nacionalidad === '') {
            $nacionalidad = null;
        } elseif (!preg_match('/^[A-Z]{3}$/', $nacionalidad)) {
            $errors[] = 'nacionalidad debe ser código ISO alpha-3';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        return;
    }

    // Mejora 8: NULLIF evita guardar strings vacíos
    $stmt = $pdo->prepare(
        'UPDATE atleta SET
            nombre       = CASE WHEN :nombre    IS NOT NULL AND :nombre    != \'\' THEN :nombre    ELSE nombre    END,
            apellido     = CASE WHEN :apellido  IS NOT NULL AND :apellido  != \'\' THEN :apellido  ELSE apellido  END,
            nacionalidad = CASE WHEN :nac       IS NOT NULL                        THEN :nac       ELSE nacionalidad END
          WHERE id_atleta = :id'
    );
    $stmt->execute([
        ':nombre'   => isset($data['nombre'])       ? atleta_sanitize(trim($data['nombre']))       : null,
        ':apellido' => isset($data['apellido'])     ? atleta_sanitize(trim($data['apellido']))     : null,
        ':nac'      => isset($data['nacionalidad']) ? ($nacionalidad ?? null)                       : null,
        ':id'       => $id,
    ]);

    // Mejora 5: rowCount() sin query extra de verificación
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Atleta no encontrado o sin cambios']);
        return;
    }

    echo json_encode(['mensaje' => 'Atleta actualizado correctamente']);
}

// -------------------------------------------------------
// DELETE — desactivar atleta (activo=0)
// Mejora 5: rowCount() sin query extra
// -------------------------------------------------------
function handleDelete(PDO $pdo): void
{
    $id_raw = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $id = ($id_raw !== null && $id_raw !== false && $id_raw > 0) ? $id_raw : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de atleta requerido']);
        return;
    }

    $stmt = $pdo->prepare('UPDATE atleta SET activo = 0 WHERE id_atleta = ? AND activo = 1');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        // Distinguir entre no existe y ya estaba desactivado
        $check = $pdo->prepare('SELECT activo FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        $atleta = $check->fetch();

        if (!$atleta) {
            http_response_code(404);
            echo json_encode(['error' => 'Atleta no encontrado']);
        } else {
            http_response_code(409);
            echo json_encode(['error' => 'El atleta ya está desactivado']);
        }
        return;
    }

    echo json_encode(['mensaje' => 'Atleta desactivado correctamente']);
}

// -------------------------------------------------------
// Mejora 19: reactivar atleta (activo=1)
// DELETE /api/atletas?id=X&reactivar=1
// -------------------------------------------------------
function handleReactivar(PDO $pdo): void
{
    $id_raw = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $id = ($id_raw !== null && $id_raw !== false && $id_raw > 0) ? $id_raw : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de atleta requerido']);
        return;
    }

    $stmt = $pdo->prepare('UPDATE atleta SET activo = 1 WHERE id_atleta = ? AND activo = 0');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT activo FROM atleta WHERE id_atleta = ?');
        $check->execute([$id]);
        $atleta = $check->fetch();

        if (!$atleta) {
            http_response_code(404);
            echo json_encode(['error' => 'Atleta no encontrado']);
        } else {
            http_response_code(409);
            echo json_encode(['error' => 'El atleta ya está activo']);
        }
        return;
    }

    echo json_encode(['mensaje' => 'Atleta reactivado correctamente']);
}
