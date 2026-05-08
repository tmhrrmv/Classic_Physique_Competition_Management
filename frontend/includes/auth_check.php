<?php
declare(strict_types=1);

// ============================================================
// includes/auth_check.php — Gestión segura de sesiones PHP
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - v1.5 - Ver versiones anteriores
// v1.6 - Sesiones guardadas en MySQL via SessionHandler
//        Soluciona problema multi-worker en Railway
//        Login PHP puro sin JavaScript
//        gc_maxlifetime 7200 (2 horas)
//        Índice en last_access para limpieza eficiente
// ============================================================

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    return false;
}

// -------------------------------------------------------
// startSecureSession()
// v1.6: usa SessionHandler para guardar sesiones en MySQL
// -------------------------------------------------------
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Cargar SessionHandler y ConexionDB
    require_once dirname(__DIR__, 2) . '/backend/clases/ConexionDB.php';
    require_once dirname(__DIR__, 2) . '/backend/clases/DbSessionHandler.php';

    // Registrar el handler de sesiones en MySQL
    $handler = new DbSessionHandler(ConexionDB::getInstancia()->getConexion());
    session_set_save_handler($handler, true);

    $isHttps = isHttpsRequest();

    ini_set('session.use_only_cookies',  '1');
    ini_set('session.use_strict_mode',   '1');
    ini_set('session.gc_maxlifetime',    '7200'); // 2 horas
    ini_set('session.gc_probability',    '1');
    ini_set('session.gc_divisor',        '100');  // 1% de peticiones ejecuta gc

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function generateSessionFingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ua);
}

function regenerateSessionNow(): void
{
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

function regenerateSessionIfNeeded(): void
{
    $interval = 300;
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
        return;
    }
    if ((time() - $_SESSION['last_regeneration']) > $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function redirectToLogin(string $reason = 'session_missing'): void
{
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    header('Location: ' . $base . '/pages/login.php?reason=' . urlencode($reason));
    exit;
}

function destroySession(string $reason = 'session_missing'): void
{
    logAccesoFallido($reason, $_SESSION['usuario'] ?? null);
    clearSessionData();
    redirectToLogin($reason);
}

function clearSessionData(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function logAccesoFallido(string $reason = 'session_missing', ?string $username = null): void
{
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $url      = $_SERVER['REQUEST_URI'] ?? 'desconocida';
    $userInfo = $username ? "usuario=$username" : 'sin_usuario';
    error_log(sprintf(
        '[AUTH] reason=%s | %s | IP=%s | URL=%s | %s',
        $reason, $userInfo, $ip, $url, date('Y-m-d H:i:s')
    ));
}

function getCurrentUser(): ?array
{
    if (!isset($_SESSION['usuario'], $_SESSION['rol'])) {
        return null;
    }
    return [
        'usuario' => $_SESSION['usuario'],
        'rol'     => $_SESSION['rol'],
        'token'   => $_SESSION['token'] ?? '',
    ];
}

function isAuthenticated(): void
{
    if (!isset($_SESSION['usuario'], $_SESSION['rol'])) {
        logAccesoFallido('session_missing');
        redirectToLogin('session_missing');
    }

    $timeout = 7200;
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            destroySession('session_expired');
        }
    }
    $_SESSION['last_activity'] = time();

    $fingerprint = generateSessionFingerprint();
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        destroySession('fingerprint_mismatch');
    }

    regenerateSessionIfNeeded();
}

function requireAuth(array $allowedRoles = []): array
{
    isAuthenticated();

    $user = getCurrentUser();
    if ($user === null) {
        destroySession('session_missing');
    }

    if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles, true)) {
        logAccesoFallido('unauthorized_role', $user['usuario']);
        redirectToLogin('unauthorized_role');
    }

    return $user;
}

// ============================================================
// INICIO
// ============================================================
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '');
}

// Cargar solo helpers y definir constantes de BD sin cargar config.php completo
// config.php envía headers que pueden romper páginas HTML
require_once dirname(__DIR__, 2) . '/backend/helpers.php';

// config.php define las constantes de BD y seguridad
// Solo cargar si no están ya definidas
if (!defined('DB_HOST')) {
    require_once dirname(__DIR__, 2) . '/backend/config.php';
}

startSecureSession();

if (!defined('AUTH_CHECK_SKIP_AUTO')) {
    $current_user = requireAuth();
}