<?php
declare(strict_types=1);

// ============================================================
// includes/set_session.php — Guarda sesión PHP tras login JWT
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Guarda $_SESSION básico tras login correcto
// v1.1 - Validación JSON body, htmlspecialchars
// v1.2 - Usa startSecureSession(), regenera ID al login,
//        guarda fingerprint, last_activity, last_regeneration
// v1.3 - Fix 3: define AUTH_CHECK_SKIP_AUTO antes de incluir
//        auth_check.php para evitar que requireAuth() se
//        ejecute automáticamente antes de guardar la sesión
// ============================================================

// Fix 3: evitar que auth_check.php fuerce autenticación
// antes de que podamos guardar los datos de sesión
define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data) || empty($data['usuario']) || empty($data['rol']) || empty($data['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos de sesión incompletos']);
    exit;
}

// Regenerar ID de sesión al hacer login
// Previene session fixation
session_regenerate_id(true);

// Guardar datos en sesión
$_SESSION['usuario']           = htmlspecialchars($data['usuario'], ENT_QUOTES, 'UTF-8');
$_SESSION['rol']               = htmlspecialchars($data['rol'],     ENT_QUOTES, 'UTF-8');
$_SESSION['token']             = $data['token'];
$_SESSION['fingerprint']       = generateSessionFingerprint();
$_SESSION['last_activity']     = time();
$_SESSION['last_regeneration'] = time();

echo json_encode(['ok' => true]);