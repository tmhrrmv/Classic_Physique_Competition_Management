<?php
declare(strict_types=1);

// ============================================================
// Configuración general del proyecto
// IMPORTANTE: no incluir credenciales reales aquí.
// Usa variables de entorno o un archivo .env fuera del repo.
// ============================================================

// Base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_competiciones');
define('DB_USER', getenv('DB_USER') ?: 'tu_usuario');
define('DB_PASS', getenv('DB_PASS') ?: 'tu_password');

// JWT / Sesiones
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'cambia_este_secreto_en_produccion');
define('JWT_TTL',    3600); // segundos

// Entorno
define('APP_ENV',   getenv('APP_ENV')   ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Cabeceras CORS para desarrollo local (ajusta en producción)
header('Content-Type: application/json; charset=utf-8');
if (APP_DEBUG) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
