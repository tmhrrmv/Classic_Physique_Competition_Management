<?php
declare(strict_types=1);

// ============================================================
// config.php — Configuración general del proyecto
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Configuración inicial
// v1.1 - Añadido JWT_TTL y APP_ENV
// v1.2 - Añadido MAX_INTENTOS y BLOQUEO_MINUTOS
//        como constantes para control de fuerza bruta big muscles =)
//        Centraliza valores antes hardcodeados en auth.php
        
// ============================================================

// -------------------------------------------------------
// Base de datos
// -------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_competiciones');
define('DB_USER', getenv('DB_USER') ?: 'tu_usuario');
define('DB_PASS', getenv('DB_PASS') ?: 'tu_password');

// -------------------------------------------------------
// JWT
// -------------------------------------------------------
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'cambia_este_secreto_en_produccion');
define('JWT_TTL',    3600); // segundos (1 hora)
define('JWT_ISS',    'gestion-competiciones');  // v1.2: issuer
define('JWT_AUD',    'backoffice');             // v1.2: audience

// -------------------------------------------------------
// Seguridad — control de fuerza bruta
// v1.2: antes hardcodeados en auth.php
// -------------------------------------------------------
define('MAX_INTENTOS',    5);  // intentos antes de bloquear
define('BLOQUEO_MINUTOS', 15); // minutos de bloqueo

// -------------------------------------------------------
// Entorno
// -------------------------------------------------------
define('APP_ENV',   getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// -------------------------------------------------------
// Cabeceras CORS
// -------------------------------------------------------
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
