<?php
// Header común — incluye en cada página
$current = $current ?? '';
if (!defined('AUTH_CHECK_SKIP_AUTO')) {
    require_once __DIR__ . '/auth_check.php';
}
$_usuario = htmlspecialchars($current_user['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$_rol     = htmlspecialchars($current_user['rol']     ?? '',         ENT_QUOTES, 'UTF-8');
$_token   = $current_user['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Classic Physique') ?> · CPF</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/theme.css">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <main class="main">
    <header class="topbar">
      <div style="font-size:0.875rem;color:var(--muted-foreground)">Federacion Nacional · Temporada 2026</div>
      <div class="user-chip">
        <div style="text-align:right">
          <div style="font-size:0.875rem;font-weight:500"><?= $_usuario ?></div>
          <div style="font-size:0.75rem;color:var(--muted-foreground)"><?= $_rol ?></div>
        </div>
        <div class="avatar"><?= strtoupper(substr($_usuario, 0, 1)) ?></div>
      </div>
    </header>
    <div class="content">
    <script>
      if (!sessionStorage.getItem('token')) sessionStorage.setItem('token', <?= json_encode($_token) ?>);
    </script>
