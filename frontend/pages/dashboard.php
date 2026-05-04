<?php
// ============================================================
// pages/dashboard.php — Panel principal del backoffice
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Panel con estadísticas generales
//        Últimas competiciones, accesos rápidos
//        Protegido con auth_check.php
// ============================================================

require_once __DIR__ . '/../includes/auth_check.php';

$usuario = htmlspecialchars($current_user['usuario'], ENT_QUOTES, 'UTF-8');
$rol     = htmlspecialchars($current_user['rol'],     ENT_QUOTES, 'UTF-8');
$token   = $current_user['token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Classic Physique</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<div class="page-wrapper">

  <!-- ======================================================
       SIDEBAR
  ====================================================== -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-text">Classic Physique</div>
      <div class="logo-sub">Competition Mgmt</div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Principal</div>
      <a href="/pages/dashboard.php"     class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>

      <div class="nav-section-title">Gestión</div>
      <a href="/pages/competiciones.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8M12 17v4M17 3H7L5 9c0 3.87 3.13 7 7 7s7-3.13 7-7l-2-6z"/></svg>
        Competiciones
      </a>
      <a href="/pages/atletas.php"       class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Atletas
      </a>
      <a href="/pages/puntuaciones.php"  class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Puntuaciones
      </a>
      <a href="/pages/resultados.php"    class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
        Resultados
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($usuario, 0, 1)) ?></div>
        <div>
          <div class="user-name"><?= $usuario ?></div>
          <div class="user-role"><?= $rol ?></div>
        </div>
      </div>
      <a href="/pages/logout.php" class="btn-logout">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- ======================================================
       CONTENIDO PRINCIPAL
  ====================================================== -->
  <main class="main-content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Bienvenido, <?= $usuario ?> — <?= $rol ?></p>
      </div>
    </div>

    <div class="page-body">

      <!-- Stats -->
      <div class="stats-grid" id="stats-grid">
        <div class="stat-card animate-in">
          <div class="stat-value" id="stat-competiciones">—</div>
          <div class="stat-label">Competiciones</div>
          <div class="stat-icon">🏆</div>
        </div>
        <div class="stat-card animate-in">
          <div class="stat-value" id="stat-atletas">—</div>
          <div class="stat-label">Atletas</div>
          <div class="stat-icon">💪</div>
        </div>
        <div class="stat-card animate-in">
          <div class="stat-value" id="stat-abiertas">—</div>
          <div class="stat-label">Abiertas</div>
          <div class="stat-icon">📅</div>
        </div>
        <div class="stat-card animate-in">
          <div class="stat-value" id="stat-cerradas">—</div>
          <div class="stat-label">Cerradas</div>
          <div class="stat-icon">✅</div>
        </div>
      </div>

      <!-- Últimas competiciones -->
      <div class="card animate-in">
        <div class="card-header">
          <span class="card-title">Últimas Competiciones</span>
          <a href="/pages/competiciones.php" class="btn btn-secondary btn-sm">Ver todas</a>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrapper" style="border:none; border-radius:0">
            <table>
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Fecha</th>
                  <th>Lugar</th>
                  <th>Estado</th>
                  <th>Inscritos</th>
                </tr>
              </thead>
              <tbody id="tabla-competiciones">
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:32px">Cargando...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Accesos rápidos -->
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:16px; margin-top:24px">
        <a href="/pages/competiciones.php" class="card animate-in" style="padding:24px; text-decoration:none; transition: border-color 0.2s">
          <div style="font-size:2rem; margin-bottom:12px">🏆</div>
          <div class="card-title" style="margin-bottom:4px">Nueva Competición</div>
          <div style="font-size:0.8rem; color:var(--text-muted)">Crear un nuevo evento</div>
        </a>
        <a href="/pages/atletas.php" class="card animate-in" style="padding:24px; text-decoration:none; transition: border-color 0.2s">
          <div style="font-size:2rem; margin-bottom:12px">💪</div>
          <div class="card-title" style="margin-bottom:4px">Inscribir Atleta</div>
          <div style="font-size:0.8rem; color:var(--text-muted)">Registrar en un evento</div>
        </a>
        <a href="/pages/puntuaciones.php" class="card animate-in" style="padding:24px; text-decoration:none; transition: border-color 0.2s">
          <div style="font-size:2rem; margin-bottom:12px">⭐</div>
          <div class="card-title" style="margin-bottom:4px">Puntuaciones</div>
          <div style="font-size:0.8rem; color:var(--text-muted)">Registrar rankings</div>
        </a>
        <a href="/pages/resultados.php" class="card animate-in" style="padding:24px; text-decoration:none; transition: border-color 0.2s">
          <div style="font-size:2rem; margin-bottom:12px">🥇</div>
          <div class="card-title" style="margin-bottom:4px">Calcular Podio</div>
          <div style="font-size:0.8rem; color:var(--text-muted)">Generar resultados finales</div>
        </a>
      </div>

    </div>
  </main>

</div>

<script>
  // Inyectar token JWT desde PHP para las llamadas API
  if (!sessionStorage.getItem('token')) {
      sessionStorage.setItem('token', '<?= addslashes($token) ?>');
  }
</script>
<script src="/js/api.js"></script>
<script src="/js/dashboard.js"></script>
</body>
</html>
