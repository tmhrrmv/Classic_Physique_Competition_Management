<?php
// ============================================================
// pages/login.php — Login PHP puro sin JavaScript obligatorio
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - v1.3 - Ver versiones anteriores
// v1.4 - Login PHP puro sin set_session.php
//        Sesión guardada directamente en MySQL
//        JavaScript solo para UX (loading, mensajes)
//        Si JS está desactivado el login sigue funcionando
// ============================================================

define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

// Si ya hay sesión activa redirigir al dashboard
if (isset($_SESSION['usuario'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error  = '';
$reason = $_GET['reason'] ?? '';

// Login por POST — PHP puro sin JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Introduce usuario y contrasena';
    } else {
        require_once __DIR__ . '/../../backend/config/db.php';

        $pdo  = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id_usuario, username, password_hash, rol, id_juez,
                    activo, intentos_fallidos, bloqueado_hasta
               FROM usuarios WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Credenciales incorrectas';
        } elseif ((int)$user['activo'] === 0) {
            $error = 'Usuario desactivado. Contacta con el administrador';
        } elseif (!empty($user['bloqueado_hasta']) && new DateTime() < new DateTime($user['bloqueado_hasta'])) {
            $mins  = (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            $error = "Cuenta bloqueada. Intenta en $mins minuto(s)";
        } elseif (!password_verify($password, $user['password_hash'])) {
            // Incrementar intentos fallidos
            $intentos = (int)$user['intentos_fallidos'] + 1;
            if ($intentos >= MAX_INTENTOS) {
                $pdo->prepare(
                    'UPDATE usuarios SET intentos_fallidos = ?,
                     bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE)
                     WHERE id_usuario = ?'
                )->execute([$intentos, $user['id_usuario']]);
                $error = 'Demasiados intentos. Cuenta bloqueada ' . BLOQUEO_MINUTOS . ' minutos';
            } else {
                $pdo->prepare(
                    'UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?'
                )->execute([$intentos, $user['id_usuario']]);
                $error = 'Credenciales incorrectas';
            }
        } else {
            // Login correcto — guardar sesión en MySQL
            session_regenerate_id(true);

            $_SESSION['usuario']           = $user['username'];
            $_SESSION['rol']               = $user['rol'];
            $_SESSION['fingerprint']       = generateSessionFingerprint();
            $_SESSION['last_activity']     = time();
            $_SESSION['last_regeneration'] = time();

            // Resetear intentos fallidos
            $pdo->prepare(
                'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL,
                 ultimo_acceso = NOW() WHERE id_usuario = ?'
            )->execute([$user['id_usuario']]);

            // También generar JWT para las llamadas API
            require_once __DIR__ . '/../../backend/middleware/auth.php';
            $token = generateJwt([
                'sub'     => $user['username'],
                'id'      => (int)$user['id_usuario'],
                'role'    => $user['rol'],
                'id_juez' => $user['id_juez'] ? (int)$user['id_juez'] : null,
                'iat'     => time(),
            ]);
            $_SESSION['token'] = $token;

            header('Location: /pages/dashboard.php');
            exit;
        }
    }
}

// Mensajes de razón de redirección
$reasonMsgs = [
    'session_expired'     => 'Tu sesion ha expirado.',
    'session_missing'     => 'Debes iniciar sesion.',
    'fingerprint_mismatch'=> 'Sesion invalida. Inicia sesion de nuevo.',
    'unauthorized_role'   => 'No tienes permiso para acceder.',
    'logout'              => 'Has cerrado sesion correctamente.',
];
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
      position: relative; z-index: 1;
      text-align: center; max-width: 340px;
    }

    .login-right h2 {
      font-size: 2.4rem; margin-bottom: 16px; line-height: 1;
      color: var(--text-primary, #f0f0f0);
      font-family: system-ui, sans-serif; text-transform: uppercase;
    }

    .login-right h2 span { color: var(--accent, #e8b84b); }
    .login-right p { color: var(--text-secondary, #999); font-size: 0.9rem; line-height: 1.7; }

    .login-features { margin-top: 40px; display: flex; flex-direction: column; gap: 14px; text-align: left; }
    .login-feature { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--text-secondary, #999); }
    .login-feature-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent, #e8b84b); flex-shrink: 0; }

    .login-box { width: 100%; max-width: 420px; }
    .login-logo { margin-bottom: 48px; }
    .login-logo .logo-text {
      font-family: system-ui, sans-serif; font-size: 1.6rem; font-weight: 800;
      letter-spacing: 0.05em; text-transform: uppercase;
      color: var(--accent, #e8b84b); line-height: 1.1;
    }
    .login-logo .logo-sub {
      font-size: 0.72rem; color: var(--text-muted, #555);
      letter-spacing: 0.18em; text-transform: uppercase; margin-top: 4px;
    }
    .login-title { font-size: 2rem; margin-bottom: 8px; color: var(--text-primary, #f0f0f0); font-family: system-ui, sans-serif; text-transform: uppercase; }
    .login-subtitle { color: var(--text-secondary, #999); font-size: 0.9rem; margin-bottom: 40px; }
    .btn-login { width: 100%; padding: 14px; font-size: 1rem; margin-top: 8px; }
    .forgot-link { text-align: center; margin-top: 20px; font-size: 0.8rem; color: var(--text-muted, #555); }

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

      <?php if ($error): ?>
        <div class="alert alert-error" style="display:flex; margin-bottom:20px">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php elseif ($reason && isset($reasonMsgs[$reason])): ?>
        <div class="alert <?= $reason === 'logout' ? 'alert-success' : 'alert-warning' ?>" style="display:flex; margin-bottom:20px">
          <?= htmlspecialchars($reasonMsgs[$reason], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/pages/login.php" novalidate>
        <div class="form-group">
          <label for="username">Usuario</label>
          <input type="text" id="username" name="username"
            placeholder="admin_user" autocomplete="username" required
            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
          <label for="password">Contrasena</label>
          <input type="password" id="password" name="password"
            placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-login">
          Entrar
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
        <div class="login-feature"><div class="login-feature-dot"></div><span>Gestion de competiciones y atletas</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Registro de puntuaciones en tiempo real</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Calculo automatico del podio</span></div>
        <div class="login-feature"><div class="login-feature-dot"></div><span>Control de acceso por roles</span></div>
      </div>
    </div>
  </div>

  <!-- JS solo para mejorar UX — no es necesario para el login -->
  <script src="/js/api.js"></script>
  <script>
    // Guardar JWT en sessionStorage para las llamadas API
    <?php if (isset($_SESSION['token'])): ?>
    sessionStorage.setItem('token', <?= json_encode($_SESSION['token']) ?>);
    <?php endif; ?>
  </script>
</body>
</html>
