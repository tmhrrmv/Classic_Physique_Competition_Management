<?php
declare(strict_types=1);

// ============================================================
// config.php
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Configuración inicial
// v1.1 - JWT_TTL y APP_ENV
// v1.2 - MAX_INTENTOS, BLOQUEO_MINUTOS, JWT_ISS, JWT_AUD
// v1.3 - JWT_SECRET forzado en producción
//        DB_PORT numérico, ALLOWED_ORIGIN desde env
// v1.4 - DB_NAME, DB_USER, DB_PASS validados
//        APP_ENV validado contra valores conocidos
// v1.5 - TRUSTED_PROXIES para validar X-Forwarded-For
//        X-Request-ID para trazabilidad
//        APP_URL para Location headers
// ============================================================

$app_env = getenv('APP_ENV') ?: 'development';
if (!in_array($app_env, ['development', 'production'], true)) {
    http_response_code(500);
    echo json_encode(['error' => 'APP_ENV debe ser development o production']);
    exit;
}
define('APP_ENV',   $app_env);
define('APP_DEBUG', APP_ENV === 'development');

// Base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
$db_port = getenv('DB_PORT') ?: '3306';
if (!ctype_digit($db_port)) {
    http_response_code(500);
    echo json_encode(['error' => 'DB_PORT debe ser un número']);
    exit;
}
define('DB_PORT', $db_port);

$db_name = getenv('DB_NAME') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';

if (APP_ENV === 'production') {
    foreach (['DB_NAME' => $db_name, 'DB_USER' => $db_user, 'DB_PASS' => $db_pass] as $key => $val) {
        if ($val === '') {
            http_response_code(500);
            echo json_encode(['error' => "$key no está configurado"]);
            exit;
        }
    }
}
define('DB_NAME', $db_name ?: 'gestion_competiciones');
define('DB_USER', $db_user ?: 'root');
define('DB_PASS', $db_pass ?: '');

// JWT
$jwt_secret = getenv('JWT_SECRET') ?: 'cambia_este_secreto_en_produccion';
if (APP_ENV === 'production' && $jwt_secret === 'cambia_este_secreto_en_produccion') {
    http_response_code(500);
    echo json_encode(['error' => 'JWT_SECRET no configurado']);
    exit;
}
define('JWT_SECRET', $jwt_secret);
define('JWT_TTL',    3600);
define('JWT_ISS',    'gestion-competiciones');
define('JWT_AUD',    'backoffice');

// Seguridad
define('MAX_INTENTOS',    5);
define('BLOQUEO_MINUTOS', 15);
define('TRUSTED_PROXIES', explode(',', getenv('TRUSTED_PROXIES') ?: '127.0.0.1,::1'));

// App URL para Location headers
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));

// Request ID para trazabilidad
$request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
if (!preg_match('/^[a-zA-Z0-9\-]{1,64}$/', $request_id)) {
    $request_id = bin2hex(random_bytes(8));
}
define('REQUEST_ID', $request_id);

// Cabeceras globales
header('Content-Type: application/json; charset=utf-8');
header('X-Request-ID: ' . REQUEST_ID);

$allowed_origin = getenv('ALLOWED_ORIGIN') ?: (APP_DEBUG ? '*' : '');
if ($allowed_origin !== '') {
    header('Access-Control-Allow-Origin: '     . $allowed_origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
