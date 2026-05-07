<?php
declare(strict_types=1);

// ============================================================
// api/auth.php — Autenticación del backoffice
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Usuarios hardcodeados
// v1.1 - Autenticación contra tabla usuarios
//        Verificación activo, bloqueado_hasta
//        Intentos fallidos, ultimo_acceso, JWT con rol/id_juez
// v1.2 - Sin intentos en respuesta, Base64 URL-safe,
//        Validación formato username
// v1.3 - getConnection() antes del primer uso
//        $ahora una sola vez, MAX_INTENTOS desde config
// v1.4 - Validación JSON body, mensaje genérico password
// v1.5 - Límite máximo password 1024, hash vacío validado
// v1.6 - Usa jsonResponse() y logError() de helpers.php
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    methodNotAllowed(['POST']);
    exit;
}

$body = file_get_contents('php://input');
$data = validateJsonBody($body);

if (!$data) {
    jsonResponse(['error' => 'El cuerpo debe ser JSON válido'], 400);
    exit;
}

if (empty($data['username']) || empty($data['password'])) {
    jsonResponse(['error' => 'username y password son requeridos'], 400);
    exit;
}

$username = trim($data['username']);
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    jsonResponse(['error' => 'Formato de username no válido'], 400);
    exit;
}

$password = $data['password'];
$pass_len = strlen(trim($password));
if ($pass_len < 8 || $pass_len > 1024) {
    jsonResponse(['error' => 'Credenciales incorrectas'], 400);
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare(
    'SELECT id_usuario, username, password_hash, rol, id_juez,
            activo, intentos_fallidos, bloqueado_hasta
       FROM usuarios WHERE username = ? LIMIT 1'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['error' => 'Credenciales incorrectas'], 401);
    exit;
}

if ((int) $user['activo'] === 0) {
    jsonResponse(['error' => 'Usuario desactivado. Contacta con el administrador'], 403);
    exit;
}

if (empty($user['password_hash'])) {
    logError('auth POST hash vacío', new \RuntimeException('password_hash vacío para: ' . $username));
    jsonResponse(['error' => 'Error interno. Contacta con el administrador'], 500);
    exit;
}

$ahora = new DateTime();

if ($user['bloqueado_hasta'] !== null) {
    $bloqueado_hasta = new DateTime($user['bloqueado_hasta']);

    if ($ahora < $bloqueado_hasta) {
        $minutos = (int) ceil(($bloqueado_hasta->getTimestamp() - $ahora->getTimestamp()) / 60);
        jsonResponse([
            'error'             => 'Cuenta bloqueada temporalmente',
            'minutos_restantes' => $minutos,
        ], 429);
        exit;
    }

    $pdo->prepare(
        'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?'
    )->execute([$user['id_usuario']]);

    $user['intentos_fallidos'] = 0;
    $user['bloqueado_hasta']   = null;
}

if (!password_verify($password, $user['password_hash'])) {
    $intentos = (int) $user['intentos_fallidos'] + 1;

    if ($intentos >= MAX_INTENTOS) {
        $pdo->prepare(
            'UPDATE usuarios SET intentos_fallidos = ?,
             bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE)
             WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        jsonResponse(['error' => 'Demasiados intentos. Cuenta bloqueada ' . BLOQUEO_MINUTOS . ' minutos'], 429);
    } else {
        $pdo->prepare(
            'UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        jsonResponse(['error' => 'Credenciales incorrectas'], 401);
    }
    exit;
}

$pdo->prepare(
    'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL,
     ultimo_acceso = NOW() WHERE id_usuario = ?'
)->execute([$user['id_usuario']]);

$token = generateJwt([
    'sub'     => $user['username'],
    'id'      => (int) $user['id_usuario'],
    'role'    => $user['rol'],
    'id_juez' => $user['id_juez'] ? (int) $user['id_juez'] : null,
    'iat'     => time(),
]);

jsonResponse([
    'token'      => $token,
    'expires_in' => JWT_TTL,
    'role'       => $user['rol'],
    'username'   => $user['username'],
]);
