<?php
// ============================================================
// pages/login.php — Página de login
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Formulario básico de login
// v1.1 - Añadido session_start()
//        Guarda $_SESSION cuando el login es correcto
//        Redirige al dashboard si ya hay sesión activa
// v1.2 - Fix 1: reemplazado session_start() por
//        startSecureSession() de auth_check.php
//        Aplica configuración segura de cookies
//      - Fix 2: verificar respuesta de set_session.php
//        antes de redirigir — evita bucle login→dashboard
//      - Fix 4: mostrar mensaje según ?reason= en URL
//        session_expired, unauthorized_role, etc.
// ============================================================

// Fix 1: usar startSecureSession() en lugar de session_start()
// Aplica HttpOnly, SameSite, Secure condicional, etc.
define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

// Si ya hay sesión activa redirigir al dashboard
if (isset($_SESSION['usuario'])) {
    header('Location: /pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Classic Physique</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="/css/styles.css">
  <style>
    body { display: flex; align-items: stretch; min-height: 100vh; overflow: hidden; }

    .login-left {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 64px;
    }

    .login-right {
      width: 45%;
      background: var(--bg-secondary);
      border-left: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 64px;
      position: relative;
      overflow: hidden;
    }

    .login-right::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse at 30% 50%, rgba(232,184,75,0.08) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 80%, rgba(232,184,75,0.04) 0%, transparent 50%);
    }

    .login-right-content {
      position: relative; z-index: 1;
      text-align: center; max-width: 340px;
    }

    .login-right-icon { font-size: 5rem; margin-bottom: 24px; display: block; opacity: 0.6; }
    .login-right h2 { font-size: 2.4rem; margin-bottom: 16px; line-height: 1; }
    .login-right h2 span { color: var(--accent); }
    .login-right p { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.7; }

    .login-features { margin-top: 40px; display: flex; flex-direction: column; gap: 14px; text-align: left; }
    .login-feature { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--text-secondary); }
    .login-feature-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }

    .login-box { width: 100%; max-width: 420px; }
    .login-logo { margin-bottom: 48px; }
    .login-logo .logo-text {
      font-family: var(--font-display); font-size: 1.6rem; font-weight: 800;
      letter-spacing: 0.05em; text-transform: uppercase; color: var(--accent); line-height: 1.1;
    }
    .login-logo .logo-sub {
      font-size: 0.72rem; color: var(--text-muted);
      letter-spacing: 0.18em; text-transform: uppercase; margin-top: 4px;
    }

    .login-title { font-size: 2rem; margin-bottom: 8px; }
    .login-subtitle {
      color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 40px;
      font-family: var(--font-body); font-weight: 400;
      text-transform: none; letter-spacing: 0;
    }

    .input-wrapper { position: relative; }
    .input-wrapper input { padding-left: 44px; }
    .input-icon {
      position: absolute; left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted); pointer-events: none; font-size: 1rem;
    }

    .btn-login { width: 100%; padding: 14px; font-size: 1rem; margin-top: 8px; }
    #login-error { display: none; margin-bottom: 20px; }
    .forgot-link { text-align: center; margin-top: 20px; font-size: 0.8rem; color: var(--text-muted); }

    .login-box > * { opacity: 0; animation: fadeInUp 0.5s ease forwards; }
    .login-box > *:nth-child(1) { animation-delay: 0.1s; }
    .login-box > *:nth-child(2) { animation-delay: 0.15s; }
    .login-box > *:nth-child(3) { animation-delay: 0.2s; }
    .login-box > *:nth-child(4) { animation-delay: 0.25s; }
    .login-box > *:nth-child(5) { animation-delay: 0.3s; }
    .login-box > *:nth-child(6) { animation-delay: 0.35s; }

    @media (max-width: 900px) {
      .login-right { display: none; }
      .login-left { padding: 40px 32px; }
    }
  </style>
</head>
<body>

  <div class="login-left">
    <div class="login-box">

      <div class="login-logo">
        <div class="logo-text">Classic Physique</div>
        <div class="logo-sub">Competition Management</div>
      </div>

      <h1 class="login-title">Acceso</h1>
      <p class="login-subtitle">Introduce tus credenciales para continuar</p>

      <div id="login-error" class="alert alert-error"></div>

      <form id="login-form" novalidate>
        <div class="form-group">
          <label for="username">Usuario</label>
          <div class="input-wrapper">
            <span class="input-icon">👤</span>
            <input type="text" id="username" name="username"
              placeholder="admin_user" autocomplete="username" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <div class="input-wrapper">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password"
              placeholder="••••••••" autocomplete="current-password" required>
          </div>
        </div>

        <button type="submit" id="btn-submit" class="btn btn-primary btn-login">
          <span id="btn-text">Entrar</span>
          <span id="btn-spinner" class="spinner" style="display:none"></span>
        </button>
      </form>

      <p class="forgot-link">Sistema de gestión — Acceso restringido</p>

    </div>
  </div>

  <div class="login-right">
    <div class="login-right-content">
      <span class="login-right-icon">🏆</span>
      <h2>Classic<br><span>Physique</span></h2>
      <p>Plataforma de gestión integral para competiciones de Classic Physique.</p>
      <div class="login-features">
        <div class="login-feature"><div class="login-feature-dot"></div><span>Gestión de competiciones y atletas</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Registro de puntuaciones en tiempo real</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Cálculo automático del podio</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Control de acceso por roles</span></div>
      </div>
    </div>
  </div>

  <script src="/js/api.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form       = document.getElementById('login-form');
        const errorEl    = document.getElementById('login-error');
        const btnSubmit  = document.getElementById('btn-submit');
        const btnText    = document.getElementById('btn-text');
        const btnSpinner = document.getElementById('btn-spinner');

        // Fix 4: mostrar mensaje según ?reason= en la URL
        // auth_check.php redirige con ?reason= cuando la sesión falla
        const params = new URLSearchParams(window.location.search);
        const reason = params.get('reason');
        const reasonMessages = {
            session_expired:     'Tu sesión ha expirado. Vuelve a iniciar sesión.',
            session_missing:     'Debes iniciar sesión para acceder.',
            fingerprint_mismatch:'Sesión inválida por seguridad. Inicia sesión de nuevo.',
            unauthorized_role:   'No tienes permiso para acceder a esa área.',
            logout:              'Has cerrado sesión correctamente.',
        };
        if (reason && reasonMessages[reason]) {
            const tipo = reason === 'logout' ? 'alert-success' : 'alert-warning';
            errorEl.className = `alert ${tipo}`;
            errorEl.textContent = reasonMessages[reason];
            errorEl.style.display = 'flex';
        }

        // Si ya hay token en sessionStorage redirigir
        if (AuthAPI.isLoggedIn()) {
            window.location.href = '/pages/dashboard.php';
            return;
        }

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearError();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showError('Introduce usuario y contraseña');
                return;
            }

            setLoading(true);

            try {
                // 1. Llamar a la API PHP para verificar credenciales
                const data = await AuthAPI.login(username, password);

                // 2. Guardar JWT en sessionStorage
                AuthAPI.saveSession(data);

                // Fix 2: verificar respuesta de set_session.php
                // antes de redirigir — evita bucle login→dashboard→login
                const sessionRes = await fetch('/includes/set_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        usuario: data.username,
                        rol:     data.role,
                        token:   data.token
                    })
                });

                if (!sessionRes.ok) {
                    throw new Error('Error al guardar la sesión en el servidor');
                }

                const sessionJson = await sessionRes.json();
                if (!sessionJson.ok) {
                    throw new Error('Respuesta inválida del servidor');
                }

                // 3. Solo redirigir si set_session fue exitoso
                window.location.href = '/pages/dashboard.php';

            } catch (err) {
                showError(err.message || 'Error al iniciar sesión');
                setLoading(false);
            }
        });

        function showError(msg) {
            if (!errorEl) return;
            errorEl.className = 'alert alert-error';
            errorEl.textContent = msg;
            errorEl.style.display = 'flex';
            errorEl.animate([
                { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' },
                { transform: 'translateX(-4px)' }, { transform: 'translateX(4px)' },
                { transform: 'translateX(0)' }
            ], { duration: 300, easing: 'ease-out' });
        }

        function clearError() {
            if (!errorEl) return;
            errorEl.style.display = 'none';
            errorEl.textContent = '';
        }

        function setLoading(loading) {
            if (!btnSubmit) return;
            btnSubmit.disabled = loading;
            if (btnText)    btnText.style.display    = loading ? 'none'        : 'inline';
            if (btnSpinner) btnSpinner.style.display = loading ? 'inline-block': 'none';
        }
    });
  </script>
</body>
</html>
