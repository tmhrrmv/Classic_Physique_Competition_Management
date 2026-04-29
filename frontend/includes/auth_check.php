<?php
declare(strict_types=1);

// ============================================================
// includes/auth_check.php — Gestión segura de sesiones PHP
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Verificación básica de sesión activa
// v1.1 - Strict session config, session_status(),
//        isAuthenticated(), getCurrentUser(),
//        session timeout, BASE_URL, logAccesoFallido()
// v1.2 - cookie_secure solo en HTTPS (no rompe localhost)
//        BASE_URL fija desde config (no HTTP_HOST)
//        Regeneración periódica del ID de sesión cada 5 min
//        getCurrentUser() devuelve null si no hay sesión
//        Fingerprint de sesión (User-Agent hash)
//        Diferenciación de motivos: session_missing,
//        session_expired, fingerprint_mismatch
//        logAccesoFallido() acepta parámetro reason
//        redirectToLogin() con ?reason= en URL
//        destroySession() centralizado
// v1.3 - Fix 1/3: eliminada ejecución automática de requireAuth()
//        Cada página llama explícitamente a requireAuth()
//        AUTH_CHECK_SKIP_AUTO para set_session.php
//      - Fix 5: añadido session.use_only_cookies
//        Evita session fixation por URL (?PHPSESSID=)
//      - Fix 6: getCurrentUser() comprobado en requireAuth()
//        Si devuelve null se destruye la sesión
//      - Fix 10: session_set_cookie_params() solo si
//        PHP_SESSION_NONE — no reconfigura sesión activa
// ============================================================

// -------------------------------------------------------
// isHttpsRequest()
// Detecta HTTPS sin depender de HTTP_HOST
// -------------------------------------------------------
function isHttpsRequest(): bool
{
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
// Fix 10: session_set_cookie_params() y ini_set()
// solo si no hay sesión activa (PHP_SESSION_NONE)
// -------------------------------------------------------
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Sesión ya iniciada — no reconfigurar
    }

    $isHttps = isHttpsRequest();

    // Fix 5: use_only_cookies evita session fixation por URL
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode',  '1');

    // Fix 10: configurar cookie solo antes de session_start()
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps, // Solo HTTPS en producción, HTTP en local
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

// -------------------------------------------------------
// generateSessionFingerprint()
// Hash del User-Agent del cliente
// -------------------------------------------------------
function generateSessionFingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ua);
}

// -------------------------------------------------------
// regenerateSessionIfNeeded()
// Regenera el ID de sesión cada 5 minutos
// -------------------------------------------------------
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

// -------------------------------------------------------
// redirectToLogin()
// Redirige al login con motivo — usa APP_BASE_URL de config
// Si APP_BASE_URL está vacía usa ruta relativa (funciona en Railway)
// -------------------------------------------------------
function redirectToLogin(string $reason = 'session_missing'): void
{
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    header('Location: ' . $base . '/pages/login.php?reason=' . urlencode($reason));
    exit;
}

// -------------------------------------------------------
// destroySession()
// Destruye sesión completa y redirige al login
// NOTA: siempre termina el script via redirectToLogin()
// Si necesitas destruir sin redirigir usa clearSessionData()
// -------------------------------------------------------
function destroySession(string $reason = 'session_missing'): void
{
    logAccesoFallido($reason, $_SESSION['usuario'] ?? null);
    clearSessionData();
    redirectToLogin($reason);
}

// -------------------------------------------------------
// clearSessionData()
// Destruye la sesión sin redirigir
// Útil para logout via API o cuando se necesita destruir
// la sesión sin terminar el script
// -------------------------------------------------------
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

// -------------------------------------------------------
// logAccesoFallido()
// Registra intento fallido con motivo, IP, URL y usuario
// -------------------------------------------------------
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

// -------------------------------------------------------
// getCurrentUser()
// Devuelve datos del usuario actual o null si no hay sesión
// null evita tratar a un invitado como usuario válido
// -------------------------------------------------------
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

// -------------------------------------------------------
// isAuthenticated()
// Verifica sesión completa:
// 1. Existencia de sesión
// 2. Timeout de inactividad (2 horas)
// 3. Fingerprint del cliente
// 4. Regeneración periódica del ID
// -------------------------------------------------------
function isAuthenticated(): bool
{
    // 1. Existencia de sesión
    if (!isset($_SESSION['usuario'], $_SESSION['rol'])) {
        logAccesoFallido('session_missing');
        redirectToLogin('session_missing');
    }

    // 2. Timeout de inactividad (2 horas)
    $timeout = 7200;
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            destroySession('session_expired');
        }
    }
    $_SESSION['last_activity'] = time();

    // 3. Fingerprint
    $fingerprint = generateSessionFingerprint();
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        destroySession('fingerprint_mismatch');
    }

    // 4. Regeneración periódica
    regenerateSessionIfNeeded();

    return true;
}

// -------------------------------------------------------
// requireAuth()
// Fix 6: comprueba getCurrentUser() después de isAuthenticated()
// Si devuelve null (edge case) destruye la sesión
// Acepta roles permitidos opcionales
// -------------------------------------------------------
function requireAuth(array $allowedRoles = []): array
{
    isAuthenticated();

    // Fix 6: comprobación explícita de getCurrentUser()
    $user = getCurrentUser();
    if ($user === null) {
        destroySession('session_missing');
    }

    // Verificar rol si se especifican roles permitidos
    if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles, true)) {
        logAccesoFallido('unauthorized_role', $user['usuario']);
        redirectToLogin('unauthorized_role');
    }

    return $user;
}

// ============================================================
// INICIO — arrancar sesión segura
// Fix 1/3: NO se ejecuta requireAuth() automáticamente
// Cada página protegida llama explícitamente a requireAuth()
// set_session.php define AUTH_CHECK_SKIP_AUTO antes de incluir
// ============================================================
startSecureSession();

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '');
}

// Fix 1: solo ejecutar requireAuth() si no se ha definido
// AUTH_CHECK_SKIP_AUTO — permite incluir este archivo en
// set_session.php sin forzar autenticación
if (!defined('AUTH_CHECK_SKIP_AUTO')) {
    $current_user = requireAuth();
}
