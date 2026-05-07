<?php
// ============================================================
// pages/login.php — Login con nuevo diseño
// ============================================================
define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

if (isset($_SESSION['usuario'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error  = '';
$reason = $_GET['reason'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Introduce usuario y contrasena';
    } else {
        require_once dirname(__DIR__, 2) . '/backend/config/db.php';
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
            $error = 'Usuario desactivado';
        } elseif (!empty($user['bloqueado_hasta']) && new DateTime() < new DateTime($user['bloqueado_hasta'])) {
            $mins  = (int) ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            $error = "Cuenta bloqueada. Intenta en $mins minuto(s)";
        } elseif (!password_verify($password, $user['password_hash'])) {
            $intentos = (int)$user['intentos_fallidos'] + 1;
            if ($intentos >= MAX_INTENTOS) {
                $pdo->prepare(
                    'UPDATE usuarios SET intentos_fallidos = ?,
                     bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE)
                     WHERE id_usuario = ?'
                )->execute([$intentos, $user['id_usuario']]);
                $error = 'Demasiados intentos. Cuenta bloqueada ' . BLOQUEO_MINUTOS . ' minutos';
            } else {
                $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?')
                    ->execute([$intentos, $user['id_usuario']]);
                $error = 'Credenciales incorrectas';
            }
        } else {
            session_regenerate_id(true);
            $_SESSION['usuario']           = $user['username'];
            $_SESSION['rol']               = $user['rol'];
            $_SESSION['fingerprint']       = generateSessionFingerprint();
            $_SESSION['last_activity']     = time();
            $_SESSION['last_regeneration'] = time();

            $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id_usuario = ?')
                ->execute([$user['id_usuario']]);

            require_once dirname(__DIR__, 2) . '/backend/middleware/auth.php';
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

$reasonMsgs = [
    'session_expired'      => 'Tu sesion ha expirado.',
    'session_missing'      => 'Debes iniciar sesion.',
    'fingerprint_mismatch' => 'Sesion invalida. Inicia sesion de nuevo.',
    'unauthorized_role'    => 'No tienes permiso para acceder.',
    'logout'               => 'Has cerrado sesion correctamente.',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso · Classic Physique</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css">
<style>
  .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
  .login-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.75rem; padding: 2.5rem; width: 100%; max-width: 420px; }
  .login-brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
  .login-brand-mark { width: 48px; height: 48px; border-radius: 0.5rem; background: var(--primary); color: var(--primary-foreground); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; }
  .login-title { font-family: "Bebas Neue"; font-size: 2rem; margin-bottom: 0.25rem; }
  .login-sub { font-size: 0.875rem; color: var(--muted-foreground); margin-bottom: 1.75rem; }
  .form-group { margin-bottom: 1rem; }
  .form-label { display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.4rem; color: var(--muted-foreground); }
  .alert { padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.85rem; margin-bottom: 1rem; }
  .alert-error { background: color-mix(in oklab, var(--destructive) 15%, transparent); color: var(--destructive); border: 1px solid color-mix(in oklab, var(--destructive) 30%, transparent); }
  .alert-success { background: color-mix(in oklab, var(--success) 15%, transparent); color: var(--success); border: 1px solid color-mix(in oklab, var(--success) 30%, transparent); }
  .alert-warning { background: color-mix(in oklab, var(--primary) 10%, transparent); color: var(--primary); border: 1px solid color-mix(in oklab, var(--primary) 25%, transparent); }
  .btn-full { width: 100%; justify-content: center; margin-top: 0.5rem; padding: 0.65rem 1rem; font-size: 0.9rem; }
  .login-footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--muted-foreground); }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <div class="login-brand">
      <div class="login-brand-mark">CP</div>
      <div>
        <div style="font-family:'Bebas Neue';font-size:1.4rem;color:var(--primary);line-height:1">CLASSIC</div>
        <div style="font-size:10px;letter-spacing:.25em;color:var(--muted-foreground);margin-top:3px">PHYSIQUE · MGMT</div>
      </div>
    </div>

    <h1 class="login-title">Acceso al backoffice</h1>
    <p class="login-sub">Sistema federativo de gestion de competiciones.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($reason && isset($reasonMsgs[$reason])): ?>
      <div class="alert <?= $reason === 'logout' ? 'alert-success' : 'alert-warning' ?>">
        <?= htmlspecialchars($reasonMsgs[$reason], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/pages/login.php">
      <div class="form-group">
        <label class="form-label" for="username">Usuario</label>
        <input class="input" type="text" id="username" name="username"
          placeholder="admin_user" autocomplete="username" required
          value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Contrasena</label>
        <input class="input" type="password" id="password" name="password"
          placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Entrar</button>
    </form>

    <p class="login-footer">Acceso restringido · Solo personal autorizado</p>
  </div>
</div>
</body>
</html>
