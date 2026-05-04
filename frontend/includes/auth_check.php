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
//        Fix 5: session.use_only_cookies
//        Fix 6: getCurrentUser() comprobado en requireAuth()
//        Fix 10: session_set_cookie_params() solo si PHP_SESSION_NONE
// v1.4 - Fix tipo: isAuthenticated() cambiado a void
//        nunca retorna false, siempre redirige si falla
//      - Añadido regenerateSessionNow() para regenerar
//        inmediatamente en cambio de rol o privilegios
//        Elimina ventana de 5 minutos de session fixation
// ============================================================

// -------------------------------------------------------
// isHttpsRequest()
// Detecta HTTPS sin depender de HTTP_HOST
// NOTA: APP_BASE_URL asume raíz del dominio
// En Railway siempre estamos en raíz, no hay subdirectorio
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
// session_set_cookie_params() e ini_set() solo si
// PHP_SESSION_NONE — no reconfigura sesión activa
// -------------------------------------------------------
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = isHttpsRequest();

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode',  '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

// -------------------------------------------------------
// generateSessionFingerprint()
// Hash del User-Agent del cliente
// NOTA: fingerprint basado solo en User-Agent
// Añadir token de sessionStorage aumentaría seguridad
// pero añade complejidad innecesaria para este proyecto
// -------------------------------------------------------
function generateSessionFingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    return hash('sha256', $ua);
}

// -------------------------------------------------------
// regenerateSessionNow()
// v1.4: regenera el ID de sesión inmediatamente
// Llamar cuando cambia el rol o los privilegios del usuario
// Elimina la ventana de 5 minutos de session fixation
// que existía con la regeneración periódica sola
// -------------------------------------------------------
function regenerateSessionNow(): void
{
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// -------------------------------------------------------
// regenerateSessionIfNeeded()
// Regenera el ID de sesión cada 5 minutos
// Para cambios de rol usar regenerateSessionNow()
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
// Redirige al login con motivo específico
// -------------------------------------------------------
function redirectToLogin(string $reason = 'session_missing'): void
{
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    header('Location: ' . $base . '/pages/login.php?reason=' . urlencode($reason));
    exit;
}

// -------------------------------------------------------
// destroySession()
// Destruye sesión y redirige — siempre termina el script
// Para destruir sin redirigir usar clearSessionData()
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
// v1.4: cambiado a void — nunca retorna false
// Si algo falla redirige directamente, nunca retorna false
// Verifica: existencia, timeout, fingerprint, regeneración
// -------------------------------------------------------
function isAuthenticated(): void
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
}

// -------------------------------------------------------
// requireAuth()
// Punto de entrada para páginas protegidas
// Acepta roles permitidos opcionales
// -------------------------------------------------------
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
startSecureSession();

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '');
}

// No ejecutar requireAuth() automáticamente
// set_session.php define AUTH_CHECK_SKIP_AUTO para saltarlo
if (!defined('AUTH_CHECK_SKIP_AUTO')) {
    $current_user = requireAuth();
}
