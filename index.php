<?php
// ============================================================
// index.php — Router principal de Railway
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Router básico: /api/ -> backend, resto -> frontend
// v1.1 - Añadido soporte para archivos estáticos
// v1.2 - ob_start() para capturar output antes de headers
// v1.3 - Fix: $_SERVER['SCRIPT_FILENAME'] y $_SERVER['PHP_SELF']
//        actualizados antes del require para que FrankenPHP
//        ejecute correctamente los archivos PHP incluidos
// ============================================================
ob_start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// -------------------------------------------------------
// Rutas de la API -> backend
// -------------------------------------------------------
if (strpos($uri, '/api/') === 0) {
    $file = __DIR__ . '/backend' . $uri;
    if (file_exists($file)) {
        ob_end_clean();
        // Actualizar variables de servidor para que FrankenPHP
        // ejecute el archivo como si fuera la entrada principal
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME']     = $uri;
        $_SERVER['PHP_SELF']        = $uri;
        require $file;
    } else {
        ob_end_clean();
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint no encontrado']);
    }
    exit;
}

// -------------------------------------------------------
// Includes del frontend (set_session, etc.)
// -------------------------------------------------------
if (strpos($uri, '/includes/') === 0) {
    $file = __DIR__ . '/frontend' . $uri;
    if (file_exists($file)) {
        ob_end_clean();
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME']     = $uri;
        $_SERVER['PHP_SELF']        = $uri;
        require $file;
    } else {
        ob_end_clean();
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint no encontrado']);
    }
    exit;
}

// -------------------------------------------------------
// Archivos estáticos — servir con Content-Type correcto
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
        ob_end_clean();
        header('Content-Type: ' . $staticTypes[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
    ob_end_clean();
    http_response_code(404);
    exit;
}

// -------------------------------------------------------
// Todo lo demás -> frontend PHP
// -------------------------------------------------------
ob_end_clean();

$file = __DIR__ . '/frontend' . ($uri === '/' ? '/index.php' : $uri);

if (file_exists($file)) {
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME']     = $uri;
    $_SERVER['PHP_SELF']        = $uri;
    require $file;
} else {
    $fallback = __DIR__ . '/frontend/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $fallback;
    require $fallback;
}
