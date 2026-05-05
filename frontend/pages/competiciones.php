<?php
// ============================================================
// pages/competiciones.php — Gestión de competiciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Estructura básica sin contenido
// v1.1 - Tabla con paginación, modal crear/editar,
//        filtro por estado, badges de estado calculado
// v1.2 - Fix PHP 1: json_encode($token) en lugar de addslashes
//      - Fix PHP 2: requireAuth() explícito
//      - Fix PHP 3: token sincronizado siempre desde PHP
//      - Fix PHP 4: window.__JWT en head, no script inline
// ============================================================

require_once __DIR__ . '/../includes/auth_check.php';

// Fix PHP 2: requireAuth() explícito — deja claro de dónde
// viene $current_user y permite especificar roles si hace falta
$current_user = requireAuth(['admin', 'organizador']);

$usuario = htmlspecialchars($current_user['usuario'], ENT_QUOTES, 'UTF-8');
$rol     = htmlspecialchars($current_user['rol'],     ENT_QUOTES, 'UTF-8');
$token   = $current_user['token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Competiciones — Classic Physique</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="/css/styles.css">
  <script>
    // Fix PHP 4: token disponible globalmente para los JS
    // Fix PHP 1: json_encode escapa correctamente cualquier string
    // Fix PHP 3: siempre sincronizar desde PHP (fuente de verdad)
    window.__JWT = <?= json_encode($token) ?>;
  </script>
  <style>
    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 14px 20px;
      border-radius: var(--radius);
      font-size: 0.875rem;
      font-weight: 500;
      z-index: 3000;
      transform: translateY(80px);
      opacity: 0;
      transition: all 0.3s ease;
      max-width: 340px;
    }
    .toast.show { transform: translateY(0); opacity: 1; }
    .toast-success { background: var(--success); color: #000; }
    .toast-error   { background: var(--error);   color: #fff; }
  </style>
</head>
<body>
<div class="page-wrapper">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-text">Classic Physique</div>
      <div class="logo-sub">Competition Mgmt</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-title">Principal</div>
      <a href="/pages/dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>
      <div class="nav-section-title">Gestion</div>
      <a href="/pages/competiciones.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M8 21h8M12 17v4M17 3H7L5 9c0 3.87 3.13 7 7 7s7-3.13 7-7l-2-6z"/>
        </svg>
        Competiciones
      </a>
      <a href="/pages/atletas.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
        </svg>
        Atletas
      </a>
      <a href="/pages/puntuaciones.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        Puntuaciones
      </a>
      <a href="/pages/resultados.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="8" r="6"/>
          <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
        </svg>
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Cerrar sesion
      </a>
    </div>
  </aside>

  <!-- CONTENIDO -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Competiciones</h1>
        <p class="page-subtitle">Gestiona los eventos de Classic Physique</p>
      </div>
      <button class="btn btn-primary" id="btn-nueva">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Nueva Competicion
      </button>
    </div>

    <div class="page-body">

      <!-- Filtros -->
      <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
        <select id="filtro-estado" style="width:auto; padding:8px 36px 8px 12px;">
          <option value="">Todos los estados</option>
          <option value="abierta">Abierta</option>
          <option value="en_curso">En curso</option>
          <option value="cerrada">Cerrada</option>
          <option value="sin_fecha">Sin fecha</option>
        </select>
      </div>

      <!-- Tabla -->
      <div class="card">
        <div class="table-wrapper" style="border:none; border-radius:0">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Evento</th>
                <th>Fecha</th>
                <th>Lugar</th>
                <th>Estado</th>
                <th>Inscritos</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-body">
              <tr>
                <td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px">
                  Cargando...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div style="padding:16px 24px; border-top:1px solid var(--border)">
          <div id="paginacion" class="pagination"></div>
        </div>
      </div>

    </div>
  </main>

</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- MODAL CREAR / EDITAR -->
<div class="modal-overlay" id="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-titulo">Nueva Competicion</span>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <div id="modal-error" class="alert alert-error" style="display:none"></div>
      <input type="hidden" id="edit-id">
      <div class="form-group">
        <label for="campo-nombre">Nombre del evento *</label>
        <input type="text" id="campo-nombre" placeholder="Torneo Apertura 2025" maxlength="200">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="campo-fecha">Fecha</label>
          <input type="date" id="campo-fecha">
        </div>
        <div class="form-group">
          <label for="campo-lugar">Lugar</label>
          <input type="text" id="campo-lugar" placeholder="Estadio Nacional" maxlength="200">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="btn-cancelar">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar">
        <span id="btn-guardar-text">Guardar</span>
        <span id="btn-guardar-spinner" class="spinner" style="display:none"></span>
      </button>
    </div>
  </div>
</div>

<script src="/js/api.js"></script>
<script src="/js/competiciones.js"></script>
</body>
</html>
