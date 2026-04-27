<?php
// Punto de entrada para Railway
// Redirige según la ruta solicitada
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Rutas de la API -> backend
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

// Todo lo demás -> frontend
$file = __DIR__ . '/frontend' . ($uri === '/' ? '/index.php' : $uri);
if (file_exists($file)) {
    require $file;
} else {
    require __DIR__ . '/frontend/index.php';
}
