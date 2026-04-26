<?php
declare(strict_types=1);

// ============================================================
// api/auth.php — Autenticación del backoffice
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Autenticación básica con usuarios hardcodeados
// v1.1 - Autenticación contra tabla usuarios de la BD
//      - Verificación de activo, bloqueado_hasta
//      - Control de intentos fallidos (máx 5, bloqueo 15 min)
//      - Actualización de ultimo_acceso en login correcto
//      - JWT incluye rol e id_juez para control de acceso
// v1.2 - Eliminado intentos_fallidos de respuestas de error
//      - Base64 URL-safe en JWT
//      - Validación de formato username
// v1.3 - getConnection() movido justo antes del primer uso
//      - new DateTime() guardada en variable $ahora
//      - MAX_INTENTOS y BLOQUEO_MINUTOS desde config.php
// v1.4 - Validación de JSON body antes de usarlo
//      - Mensaje genérico en validación de password
// v1.5 - Mejora 3: verificar que password_hash no sea vacío
//        o NULL antes de llamar a password_verify
//        Evita comportamiento inesperado con hashes inválidos
//      - Mejora 5: límite de longitud máxima de password (1024)
//        Evita ataque DoS enviando contraseñas enormes para
//        hacer lenta la verificación bcrypt
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// -------------------------------------------------------
// Validar que el body sea JSON válido
// -------------------------------------------------------
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'El cuerpo de la petición debe ser JSON válido']);
    exit;
}

if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'username y password son requeridos']);
    exit;
}

// -------------------------------------------------------
// Validar formato de username
// Solo permite letras, números y guiones bajos (3-50 chars)
// -------------------------------------------------------
$username = trim($data['username']);

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de username no válido']);
    exit;
}

// -------------------------------------------------------
// Validar password
// Mínimo 8 chars — mensaje genérico para no revelar política
// v1.5 mejora 5: máximo 1024 chars para evitar DoS con bcrypt
// -------------------------------------------------------
$password = $data['password'];
$pass_len = strlen(trim($password));

if ($pass_len < 8 || $pass_len > 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

// -------------------------------------------------------
// getConnection() justo antes del primer uso real
// -------------------------------------------------------
$pdo = getConnection();

// -------------------------------------------------------
// 1. Buscar usuario en la BD
// username tiene UNIQUE KEY que actúa como índice
// -------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id_usuario, username, password_hash, rol, id_juez,
            activo, intentos_fallidos, bloqueado_hasta
       FROM usuarios
      WHERE username = ?
      LIMIT 1'
);
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

// -------------------------------------------------------
// 2. Verificar que el usuario está activo
// -------------------------------------------------------
if ((int) $user['activo'] === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Usuario desactivado. Contacta con el administrador']);
    exit;
}

// -------------------------------------------------------
// v1.5 mejora 3: verificar que password_hash sea válido
// antes de llamar a password_verify — un hash vacío o NULL
// daría comportamiento inesperado
// -------------------------------------------------------
if (empty($user['password_hash'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno. Contacta con el administrador']);
    exit;
}

// -------------------------------------------------------
// 3. Verificar bloqueo por intentos fallidos
// $ahora instanciado una sola vez y reutilizado
// -------------------------------------------------------
$ahora = new DateTime();

if ($user['bloqueado_hasta'] !== null) {
    $bloqueado_hasta = new DateTime($user['bloqueado_hasta']);

    if ($ahora < $bloqueado_hasta) {
        $minutos_restantes = (int) ceil(
            ($bloqueado_hasta->getTimestamp() - $ahora->getTimestamp()) / 60
        );
        http_response_code(429);
        echo json_encode([
            'error'             => 'Cuenta bloqueada temporalmente por exceso de intentos fallidos',
            'minutos_restantes' => $minutos_restantes,
        ]);
        exit;
    }

    $pdo->prepare(
        'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?'
    )->execute([$user['id_usuario']]);

    $user['intentos_fallidos'] = 0;
    $user['bloqueado_hasta']   = null;
}

// -------------------------------------------------------
// 4. Verificar contraseña
// -------------------------------------------------------
if (!password_verify($password, $user['password_hash'])) {

    $intentos = (int) $user['intentos_fallidos'] + 1;

    if ($intentos >= MAX_INTENTOS) {
        $pdo->prepare(
            'UPDATE usuarios
                SET intentos_fallidos = ?,
                    bloqueado_hasta   = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE)
              WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        http_response_code(429);
        echo json_encode([
            'error' => 'Demasiados intentos fallidos. Cuenta bloqueada ' . BLOQUEO_MINUTOS . ' minutos',
        ]);
    } else {
        $pdo->prepare(
            'UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?'
        )->execute([$intentos, $user['id_usuario']]);

        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
    }
    exit;
}

// -------------------------------------------------------
// 5. Login correcto — resetear intentos y actualizar acceso
// -------------------------------------------------------
$pdo->prepare(
    'UPDATE usuarios
        SET intentos_fallidos = 0,
            bloqueado_hasta   = NULL,
            ultimo_acceso     = NOW()
      WHERE id_usuario = ?'
)->execute([$user['id_usuario']]);

// -------------------------------------------------------
// 6. Generar JWT con rol e id_juez
// -------------------------------------------------------
$token = generateJwt([
    'sub'     => $user['username'],
    'id'      => (int) $user['id_usuario'],
    'role'    => $user['rol'],
    'id_juez' => $user['id_juez'] ? (int) $user['id_juez'] : null,
    'iat'     => time(),
]);

echo json_encode([
    'token'      => $token,
    'expires_in' => JWT_TTL,
    'role'       => $user['rol'],
    'username'   => $user['username'],
]);
