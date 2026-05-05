<?php
// ============================================================
// pages/login.php — Página de login
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Formulario básico de login
// v1.1 - session_start(), $_SESSION, redirección
// v1.2 - startSecureSession(), set_session.php, ?reason=
// v1.3 - Eliminadas animaciones fadeInUp que causaban
//        opacity:0 permanente si styles.css fallaba
//        CSS completamente inline, sin dependencia externa
//        Sin emojis
// ============================================================

define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

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
  <link rel="stylesheet" href="/css/styles.css">
  <style>
    body {
      display: flex;
      align-items: stretch;
      min-height: 100vh;
      overflow: hidden;
    }

    .login-left {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 64px;
      background: var(--bg-primary, #0f0f0f);
    }

    .login-right {
      width: 45%;
      background: var(--bg-secondary, #111);
      border-left: 1px solid var(--border, #2a2a2a);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 64px;
      position: relative;
      overflow: hidden;
    }

    .login-right-content {
      position: relative;
      z-index: 1;
      text-align: center;
      max-width: 340px;
    }

    .login-right h2 {
      font-size: 2.4rem;
      margin-bottom: 16px;
      line-height: 1;
      color: var(--text-primary, #f0f0f0);
      font-family: system-ui, sans-serif;
      text-transform: uppercase;
    }

    .login-right h2 span { color: var(--accent, #e8b84b); }

    .login-right p {
      color: var(--text-secondary, #999);
      font-size: 0.9rem;
      line-height: 1.7;
    }

    .login-features {
      margin-top: 40px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      text-align: left;
    }

    .login-feature {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.85rem;
      color: var(--text-secondary, #999);
    }

    .login-feature-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--accent, #e8b84b);
      flex-shrink: 0;
    }

    .login-box {
      width: 100%;
      max-width: 420px;
    }

    .login-logo { margin-bottom: 48px; }

    .login-logo .logo-text {
      font-family: system-ui, sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: var(--accent, #e8b84b);
      line-height: 1.1;
    }

    .login-logo .logo-sub {
      font-size: 0.72rem;
      color: var(--text-muted, #555);
      letter-spacing: 0.18em;
      text-transform: uppercase;
      margin-top: 4px;
    }

    .login-title {
      font-size: 2rem;
      margin-bottom: 8px;
      color: var(--text-primary, #f0f0f0);
      font-family: system-ui, sans-serif;
      text-transform: uppercase;
    }

    .login-subtitle {
      color: var(--text-secondary, #999);
      font-size: 0.9rem;
      margin-bottom: 40px;
    }

    .input-wrapper { position: relative; }

    .btn-login {
      width: 100%;
      padding: 14px;
      font-size: 1rem;
      margin-top: 8px;
    }

    #login-error { display: none; margin-bottom: 20px; }

    .forgot-link {
      text-align: center;
      margin-top: 20px;
      font-size: 0.8rem;
      color: var(--text-muted, #555);
    }

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
          <input type="text" id="username" name="username"
            placeholder="admin_user" autocomplete="username" required>
        </div>

        <div class="form-group">
          <label for="password">Contrasena</label>
          <input type="password" id="password" name="password"
            placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button type="submit" id="btn-submit" class="btn btn-primary btn-login">
          <span id="btn-text">Entrar</span>
          <span id="btn-spinner" class="spinner" style="display:none"></span>
        </button>
      </form>

      <p class="forgot-link">Sistema de gestion — Acceso restringido</p>

    </div>
  </div>

  <div class="login-right">
    <div class="login-right-content">
      <h2>Classic<br><span>Physique</span></h2>
      <p>Plataforma de gestion integral para competiciones de Classic Physique.</p>
      <div class="login-features">
        <div class="login-feature">
          <div class="login-feature-dot"></div>
          <span>Gestion de competiciones y atletas</span>
        </div>
        <div class="login-feature">
          <div class="login-feature-dot"></div>
          <span>Registro de puntuaciones en tiempo real</span>
        </div>
        <div class="login-feature">
          <div class="login-feature-dot"></div>
          <span>Calculo automatico del podio</span>
        </div>
        <div class="login-feature">
          <div class="login-feature-dot"></div>
          <span>Control de acceso por roles</span>
        </div>
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

        const params = new URLSearchParams(window.location.search);
        const reason = params.get('reason');
        const msgs = {
            session_expired:      'Tu sesion ha expirado.',
            session_missing:      'Debes iniciar sesion.',
            fingerprint_mismatch: 'Sesion invalida. Inicia sesion de nuevo.',
            unauthorized_role:    'No tienes permiso para acceder.',
            logout:               'Has cerrado sesion correctamente.',
        };
        if (reason && msgs[reason]) {
            errorEl.className = 'alert ' + (reason === 'logout' ? 'alert-success' : 'alert-warning');
            errorEl.textContent = msgs[reason];
            errorEl.style.display = 'flex';
        }

        if (typeof AuthAPI !== 'undefined' && AuthAPI.isLoggedIn()) {
            window.location.href = '/pages/dashboard.php';
            return;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorEl.style.display = 'none';

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showError('Introduce usuario y contrasena');
                return;
            }

            setLoading(true);

            try {
                const data = await AuthAPI.login(username, password);
                AuthAPI.saveSession(data);

                const res = await fetch('/includes/set_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        usuario: data.username,
                        rol:     data.role,
                        token:   data.token
                    })
                });

                if (!res.ok) throw new Error('Error al guardar la sesion');
                const json = await res.json();
                if (!json.ok) throw new Error('Error en el servidor');

                window.location.href = '/pages/dashboard.php';

            } catch (err) {
                showError(err.message || 'Error al iniciar sesion');
                setLoading(false);
            }
        });

        function showError(msg) {
            errorEl.className = 'alert alert-error';
            errorEl.textContent = msg;
            errorEl.style.display = 'flex';
        }

        function setLoading(on) {
            btnSubmit.disabled       = on;
            btnText.style.display    = on ? 'none'         : 'inline';
            btnSpinner.style.display = on ? 'inline-block' : 'none';
        }
    });
  </script>
</body>
</html>
