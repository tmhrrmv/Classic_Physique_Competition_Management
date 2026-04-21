<?php
declare(strict_types=1);

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
// Autenticación contra tabla de usuarios (pendiente crear)
// Por ahora se usa un usuario de ejemplo para desarrollo.
// Sustituir por consulta real a la base de datos.
// -------------------------------------------------------
$users = [
    'admin' => ['password_hash' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin'],
    'juez1' => ['password_hash' => password_hash('juez123',  PASSWORD_DEFAULT), 'role' => 'juez'],
];

$username = $data['username'];

if (!isset($users[$username]) || !password_verify($data['password'], $users[$username]['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

$token = generateJwt([
    'sub'      => $username,
    'role'     => $users[$username]['role'],
    'iat'      => time(),
]);

echo json_encode([
    'token'      => $token,
    'expires_in' => JWT_TTL,
    'role'       => $users[$username]['role'],
]);
