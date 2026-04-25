<?php
declare(strict_types=1);

// ============================================================
// api/auth.php — Autenticación del backoffice
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Autenticación básica con usuarios hardcodeados
// v1.1 - Autenticación contra tabla usuarios de la BD
//      - Verificación de activo, bloqueado_hasta
//      - Control de intentos fallidos (máx 5, bloqueo 15 min)
//      - Actualización de ultimo_acceso en login correcto
//      - JWT incluye rol e id_juez para control de acceso
// v1.2 - Eliminado intentos_fallidos de respuestas de error
//      - Base64 URL-safe en JWT
//      - Validación de formato username
// v1.3 - Mejora 1: validación longitud mínima de password (8 chars)
//      - Mejora 3: getConnection() movido justo antes del primer uso
//      - Mejora 4: new DateTime() guardada en variable $ahora
//        para no instanciarla dos veces
//      - MAX_INTENTOS y BLOQUEO_MINUTOS leídos desde config.php
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'username y password son requeridos']);
    exit;
}

// -------------------------------------------------------
// Validar formato de username
// Solo permite letras, números y guiones bajos (3-50 chars)
// -------------------------------------------------------
$username = trim($data['username']);

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de username no válido']);
    exit;
}

// -------------------------------------------------------
// v1.3 mejora 1: validar longitud mínima de password
// empty() no detecta '        ' (espacios) como vacío
// -------------------------------------------------------
$password = $data['password'];

if (strlen(trim($password)) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

// -------------------------------------------------------
// v1.3 mejora 3: getConnection() justo antes del primer uso
// Si las validaciones anteriores fallan no se abre conexión
// -------------------------------------------------------
$pdo = getConnection();

// -------------------------------------------------------
// 1. Buscar usuario en la BD
// -------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id_usuario, username, password_hash, rol, id_juez,
            activo, intentos_fallidos, bloqueado_hasta
       FROM usuarios
      WHERE username = ?
      LIMIT 1'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

// Mismo mensaje que contraseña incorrecta
// para no revelar si el usuario existe o no
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

// -------------------------------------------------------
// 2. Verificar que el usuario está activo
// -------------------------------------------------------
if ((int) $user['activo'] === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Usuario desactivado. Contacta con el administrador']);
    exit;
}

// -------------------------------------------------------
// 3. Verificar bloqueo por intentos fallidos
// v1.3 mejora 4: $ahora instanciado una sola vez
// -------------------------------------------------------
$ahora = new DateTime();

if ($user['bloqueado_hasta'] !== null) {
    $bloqueado_hasta = new DateTime($user['bloqueado_hasta']);

    if ($ahora < $bloqueado_hasta) {
        $minutos_restantes = (int) ceil(
            ($bloqueado_hasta->getTimestamp() - $ahora->getTimestamp()) / 60
        );
        http_response_code(429);
        echo json_encode([
            'error'             => 'Cuenta bloqueada temporalmente por exceso de intentos fallidos',
            'minutos_restantes' => $minutos_restantes,
        ]);
        exit;
    }

    // El bloqueo expiró — resetear
    $pdo->prepare(
        'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?'
    )->execute([$user['id_usuario']]);

    $user['intentos_fallidos'] = 0;
    $user['bloqueado_hasta']   = null;
}

// -------------------------------------------------------
// 4. Verificar contraseña
// MAX_INTENTOS y BLOQUEO_MINUTOS desde config.php
// -------------------------------------------------------
if (!password_verify($password, $user['password_hash'])) {

    $intentos = (int) $user['intentos_fallidos'] + 1;

    if ($intentos >= MAX_INTENTOS) {
        $pdo->prepare(
            'UPDATE usuarios
                SET intentos_fallidos = ?,
                    bloqueado_hasta   = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE)
              WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        http_response_code(429);
        echo json_encode([
            'error' => 'Demasiados intentos fallidos. Cuenta bloqueada ' . BLOQUEO_MINUTOS . ' minutos',
        ]);
    } else {
        $pdo->prepare(
            'UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
    }
    exit;
}

// -------------------------------------------------------
// 5. Login correcto — resetear intentos y actualizar acceso
// -------------------------------------------------------
$pdo->prepare(
    'UPDATE usuarios
        SET intentos_fallidos = 0,
            bloqueado_hasta   = NULL,
            ultimo_acceso     = NOW()
      WHERE id_usuario = ?'
)->execute([$user['id_usuario']]);

// -------------------------------------------------------
// 6. Generar JWT con rol e id_juez
//    iss y aud desde config.php (v1.3)
// -------------------------------------------------------
$token = generateJwt([
    'sub'     => $user['username'],
    'id'      => (int) $user['id_usuario'],
    'role'    => $user['rol'],
    'id_juez' => $user['id_juez'] ? (int) $user['id_juez'] : null,
    'iat'     => time(),
]);

echo json_encode([
    'token'      => $token,
    'expires_in' => JWT_TTL,
    'role'       => $user['rol'],
    'username'   => $user['username'],
]);
