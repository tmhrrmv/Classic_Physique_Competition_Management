<?php
declare(strict_types=1);

// ============================================================
// api/auth.php — Autenticación del backoffice
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Usuarios hardcodeados
// v1.1 - Autenticación contra tabla usuarios
// v1.2 - Sin intentos en respuesta, Base64 URL-safe
// v1.3 - getConnection() antes del primer uso
// v1.4 - Validación JSON body, mensaje genérico password
// v1.5 - Límite máximo password 1024, hash vacío validado
// v1.6 - Usa jsonResponse() y logError() de helpers.php
// v1.7 - Añadido ?action=session para guardar sesión PHP
//        tras recibir JWT. Datos del token verificado,
//        nunca del body. Comprobación activo en BD.
// v1.8 - Fix: verifyJwt devuelve ['payload'=>...] no el payload
//        directo. Corregido acceso a sub/role/id_juez.
// v1.9 - Fix: ?action=session ahora usa DbSessionHandler (MySQL)
//        igual que auth_check.php. Con session_start() nativo
//        la sesión se guardaba en archivos pero se leía de MySQL.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    methodNotAllowed(['POST']);
    exit;
}

// -------------------------------------------------------
// ?action=session — guardar sesión PHP tras login JWT
// Los datos de sesión vienen del token verificado,
// nunca del body, para evitar falsificación de rol.
// -------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'session') {
    $body = file_get_contents('php://input');
    $data = validateJsonBody($body);

    if (!$data || empty($data['token'])) {
        jsonResponse(['error' => 'Token requerido'], 400);
        exit;
    }

    // Verificar JWT — rol y usuario se extraen del token
    $decoded = verifyJwt($data['token']);
    if (isset($decoded['error'])) {
        jsonResponse(['error' => $decoded['error']], 401);
        exit;
    }
    $payload = $decoded['payload'];
    if (empty($payload['sub']) || empty($payload['role'])) {
        jsonResponse(['error' => 'Token inválido o expirado'], 401);
        exit;
    }

    // Verificar que el usuario sigue activo en la BD
    $pdo  = getConnection();
    $stmt = $pdo->prepare(
        'SELECT id_usuario FROM usuarios
          WHERE username = ? AND activo = 1 LIMIT 1'
    );
    $stmt->execute([$payload['sub']]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Usuario no encontrado o desactivado'], 403);
        exit;
    }

    // Sesión con MySQL handler — mismo store que auth_check.php
    // (necesario para multi-worker FrankenPHP en Railway)
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../clases/DbSessionHandler.php';
        $sessionHandler = new DbSessionHandler(ConexionDB::getInstancia()->getConexion());
        session_set_save_handler($sessionHandler, true);

        $isHttps = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode',  '1');
        ini_set('session.gc_maxlifetime',   '7200');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    session_regenerate_id(true);

    $_SESSION['usuario']           = $payload['sub'];
    $_SESSION['rol']               = $payload['role'];
    $_SESSION['token']             = $data['token'];
    $_SESSION['id_juez']           = $payload['id_juez'] ?? null;
    $_SESSION['fingerprint']       = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $_SESSION['last_activity']     = time();
    $_SESSION['last_regeneration'] = time();

    jsonResponse(['ok' => true]);
    exit;
}

// -------------------------------------------------------
// Login normal — verificar credenciales y devolver JWT
// -------------------------------------------------------
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
