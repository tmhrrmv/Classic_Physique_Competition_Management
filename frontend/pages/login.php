<?php
// ============================================================
// pages/login.php — Página de login
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Formulario básico
// v1.1 - Sesiones PHP + JWT
// v1.2 - Mensajes por ?reason=, startSecureSession()
// v1.3 - Campo usuario vacío por defecto
//        Eliminado texto "Acceso restringido"
// ============================================================
define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

// Si ya hay sesión activa redirigir al dashboard
// Comprobación directa para evitar bucle de redirecciones
if (!empty($_SESSION['usuario']) && !empty($_SESSION['rol'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$token = $current_user['token'] ?? '';

// Mensajes según el motivo de redirección
$reason  = filter_input(INPUT_GET, 'reason', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$mensaje = '';
$tipo    = '';
switch ($reason) {
    case 'session_expired':
        $mensaje = 'Tu sesión ha expirado. Inicia sesión de nuevo.';
        $tipo    = 'warning';
        break;
    case 'session_missing':
        $mensaje = 'Debes iniciar sesión para acceder.';
        $tipo    = 'warning';
        break;
    case 'unauthorized_role':
        $mensaje = 'No tienes permiso para acceder a esa sección.';
        $tipo    = 'error';
        break;
    case 'fingerprint_mismatch':
        $mensaje = 'Sesión inválida por seguridad. Inicia sesión de nuevo.';
        $tipo    = 'error';
        break;
    case 'logout':
        $mensaje = 'Has cerrado sesión correctamente.';
        $tipo    = 'success';
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — Classic Physique</title>
  <link rel="stylesheet" href="/css/theme.css">
  <style>
    body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--background); padding:1rem; }
    .login-card { background:var(--card); border:1px solid var(--border); border-radius:.75rem; width:100%; max-width:400px; padding:2rem; }
    .login-logo { font-family:'Bebas Neue',sans-serif; font-size:2rem; color:var(--primary); letter-spacing:.1em; margin-bottom:.25rem; }
    .login-sub  { font-size:.85rem; color:var(--muted-foreground); margin-bottom:2rem; }
    .form-group { margin-bottom:1rem; }
    .form-label { display:block; font-size:.8rem; font-weight:500; color:var(--muted-foreground); margin-bottom:.4rem; }
    .alert { padding:.75rem 1rem; border-radius:.375rem; font-size:.85rem; margin-bottom:1rem; }
    .alert-warning { background:color-mix(in oklab,oklch(0.8 0.15 80) 15%,transparent); color:oklch(0.8 0.15 80); border:1px solid color-mix(in oklab,oklch(0.8 0.15 80) 30%,transparent); }
    .alert-error   { background:color-mix(in oklab,var(--destructive) 15%,transparent); color:var(--destructive); border:1px solid color-mix(in oklab,var(--destructive) 30%,transparent); }
    .alert-success { background:color-mix(in oklab,oklch(0.7 0.15 150) 15%,transparent); color:oklch(0.7 0.15 150); border:1px solid color-mix(in oklab,oklch(0.7 0.15 150) 30%,transparent); }
  </style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">CLASSIC PHYSIQUE</div>
  <div class="login-sub">Panel de gestión de competiciones</div>

  <?php if ($mensaje): ?>
  <div class="alert alert-<?= $tipo ?>">
    <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
  </div>
  <?php endif; ?>

  <div id="login-error" style="display:none" class="alert alert-error"></div>

  <div class="form-group">
    <label class="form-label" for="username">Usuario</label>
    <input class="input" type="text" id="username" name="username"
           autocomplete="username" placeholder="Usuario">
  </div>
  <div class="form-group">
    <label class="form-label" for="password">Contraseña</label>
    <input class="input" type="password" id="password" name="password"
           autocomplete="current-password" placeholder="Contraseña">
  </div>

  <button class="btn btn-primary" id="btn-login" style="width:100%;margin-top:.5rem">
    <span id="btn-text">Entrar</span>
    <span id="btn-spinner" style="display:none">Cargando...</span>
  </button>

  <div style="margin-top:1rem;text-align:center">
    <span style="font-size:.8rem;color:var(--muted-foreground)">¿Solo quieres ver resultados?</span>
    <br>
    <button id="btn-publico" style="background:none;border:none;color:var(--primary);font-size:.85rem;cursor:pointer;margin-top:.35rem;text-decoration:underline">
      Continuar sin cuenta
    </button>
  </div>
</div>

<script>
  // Limpiar token si viene de logout
  <?php if ($reason === 'logout'): ?>
  sessionStorage.removeItem('token');
  <?php endif; ?>

  document.addEventListener('DOMContentLoaded', () => {
    const errEl    = document.getElementById('login-error');
    const btnLogin = document.getElementById('btn-login');
    const btnText  = document.getElementById('btn-text');
    const btnSpinner = document.getElementById('btn-spinner');

    // Enter en cualquier campo
    ['username','password'].forEach(id => {
      document.getElementById(id).addEventListener('keydown', e => {
        if (e.key === 'Enter') doLogin();
      });
    });

    btnLogin.addEventListener('click', doLogin);

    // Continuar sin cuenta — login automático como publico
    document.getElementById('btn-publico').addEventListener('click', () => {
      document.getElementById('username').value = 'publico';
      document.getElementById('password').value = 'Publico2025!';
      doLogin();
    });

    async function doLogin() {
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;

      if (!username || !password) {
        showError('Introduce usuario y contraseña');
        return;
      }

      btnLogin.disabled  = true;
      btnText.style.display   = 'none';
      btnSpinner.style.display = 'inline';
      errEl.style.display = 'none';

      try {
        const res  = await fetch('/api/auth.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ username, password })
        });
        const json = await res.json();

        if (!res.ok) {
          showError(json.error || 'Credenciales incorrectas');
          return;
        }

        // Guardar token en sessionStorage
        sessionStorage.setItem('token', json.token);

        // Guardar sesión PHP via API
        const sesRes = await fetch('/api/auth.php?action=session', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({
            token:   json.token,
            usuario: json.username,
            rol:     json.role,
            id_juez: json.id_juez ?? null
          })
        });
        const sesJson = await sesRes.json();

        if (!sesRes.ok || !sesJson.ok) {
          showError(sesJson.error || 'Error al iniciar sesión');
          return;
        }

        window.location.href = '/pages/dashboard.php';

      } catch(e) {
        showError('Error de conexión con el servidor');
      } finally {
        btnLogin.disabled    = false;
        btnText.style.display    = 'inline';
        btnSpinner.style.display = 'none';
      }
    }

    function showError(msg) {
      errEl.textContent   = msg;
      errEl.style.display = 'block';
    }
  });
</script>
</body>
</html>
