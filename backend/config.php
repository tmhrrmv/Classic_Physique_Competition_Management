<?php
declare(strict_types=1);

// ============================================================
// config.php — Configuración general del proyecto
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Configuración inicial
// v1.1 - Añadido JWT_TTL y APP_ENV
// v1.2 - Añadido MAX_INTENTOS, BLOQUEO_MINUTOS, JWT_ISS, JWT_AUD
// v1.3 - JWT_SECRET lanza error en producción si no se cambia
//      - DB_PORT validado como numérico
//      - ALLOWED_ORIGIN desde variable de entorno
// v1.4 - Mejora 1: DB_NAME, DB_USER, DB_PASS validados
//        Si están vacíos lanza error claro en lugar de
//        un error críptico de conexión más adelante
//      - Mejora 2: APP_ENV validado contra valores conocidos
//        Solo acepta 'development' o 'production'
// ============================================================

// -------------------------------------------------------
// v1.4 mejora 2: APP_ENV validado contra valores conocidos
// -------------------------------------------------------
$app_env = getenv('APP_ENV') ?: 'development';

if (!in_array($app_env, ['development', 'production'], true)) {
    http_response_code(500);
    echo json_encode(['error' => 'APP_ENV debe ser development o production']);
    exit;
}

define('APP_ENV',   $app_env);
define('APP_DEBUG', APP_ENV === 'development');

// -------------------------------------------------------
// Base de datos
// v1.4 mejora 1: validar que DB_NAME, DB_USER, DB_PASS
// no estén vacíos — error claro antes de intentar conectar
// -------------------------------------------------------
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
            echo json_encode(['error' => "$key no está configurado. Revisa las variables de entorno"]);
            exit;
        }
    }
}

define('DB_NAME', $db_name ?: 'gestion_competiciones');
define('DB_USER', $db_user ?: 'tu_usuario');
define('DB_PASS', $db_pass ?: 'tu_password');

// -------------------------------------------------------
// JWT
// JWT_SECRET fuerza error en producción si no se ha cambiado
// -------------------------------------------------------
$jwt_secret = getenv('JWT_SECRET') ?: 'cambia_este_secreto_en_produccion';

if (APP_ENV === 'production' && $jwt_secret === 'cambia_este_secreto_en_produccion') {
    http_response_code(500);
    echo json_encode(['error' => 'JWT_SECRET no configurado. Revisa las variables de entorno']);
    exit;
}

define('JWT_SECRET', $jwt_secret);
define('JWT_TTL',    3600);
define('JWT_ISS',    'gestion-competiciones');
define('JWT_AUD',    'backoffice');

// -------------------------------------------------------
// Seguridad — control de fuerza bruta
// -------------------------------------------------------
define('MAX_INTENTOS',    5);
define('BLOQUEO_MINUTOS', 15);

// -------------------------------------------------------
// Cabeceras CORS
// En producción solo el origen definido en ALLOWED_ORIGIN
// -------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

if (APP_DEBUG) {
    $allowed_origin = getenv('ALLOWED_ORIGIN') ?: '*';
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
} else {
    $allowed_origin = getenv('ALLOWED_ORIGIN') ?: '';
    if ($allowed_origin !== '') {
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
