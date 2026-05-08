<?php
// ============================================================
// index.php — Router principal de Railway
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Router básico: /api/ -> backend, resto -> frontend
// v1.1 - Añadido soporte para archivos estáticos
//        CSS, JS, imágenes y fuentes se sirven con
//        Content-Type correcto en lugar de text/html
//        Sin esto el navegador ignora los estilos CSS
// ============================================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// -------------------------------------------------------
// Rutas de la API -> backend
// -------------------------------------------------------
if (strpos($uri, '/api/') === 0) {
    $file = __DIR__ . '/backend' . $uri;
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado']);
    }
    exit;
}

// -------------------------------------------------------
// Archivos estáticos — servir con Content-Type correcto
// Sin esto el router los sirve como text/html y el
// navegador ignora los estilos CSS y scripts JS
// -------------------------------------------------------
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$staticTypes = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
];

if (isset($staticTypes[$ext])) {
    $file = __DIR__ . '/frontend' . $uri;
    if (file_exists($file)) {
        header('Content-Type: ' . $staticTypes[$ext]);
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

// -------------------------------------------------------
// Todo lo demás -> frontend PHP
// -------------------------------------------------------
$file = __DIR__ . '/frontend' . ($uri === '/' ? '/index.php' : $uri);
if (file_exists($file)) {
    require $file;
} else {
    require __DIR__ . '/frontend/index.php';
}