<?php
// ============================================================
// pages/atletas.php — Gestión de atletas e inscripciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Estructura básica sin contenido
// v1.1 - Tabla de atletas con paginación
//        Modal inscribir atleta en evento
//        Modal ver historial del atleta
//        Filtro activos/inactivos
//        Botones desactivar/reactivar
// ============================================================

require_once __DIR__ . '/../includes/auth_check.php';

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
  <title>Atletas — Classic Physique</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="/css/styles.css">
  <script>
    window.__JWT = <?= json_encode($token) ?>;
  </script>
  <style>
    .toast {
      position: fixed; bottom: 24px; right: 24px;
      padding: 14px 20px; border-radius: var(--radius);
      font-size: 0.875rem; font-weight: 500;
      z-index: 3000; transform: translateY(80px); opacity: 0;
      transition: all 0.3s ease; max-width: 340px;
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
      <a href="/pages/competiciones.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M8 21h8M12 17v4M17 3H7L5 9c0 3.87 3.13 7 7 7s7-3.13 7-7l-2-6z"/>
        </svg>
        Competiciones
      </a>
      <a href="/pages/atletas.php" class="nav-item active">
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
        <h1 class="page-title">Atletas</h1>
        <p class="page-subtitle">Gestiona atletas e inscripciones</p>
      </div>
      <button class="btn btn-primary" id="btn-inscribir">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Inscribir Atleta
      </button>
    </div>

    <div class="page-body">

      <!-- Filtros -->
      <div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
        <select id="filtro-activo" style="width:auto; padding:8px 36px 8px 12px;">
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>

      <!-- Tabla -->
      <div class="card">
        <div class="table-wrapper" style="border:none; border-radius:0">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Fecha Nac.</th>
                <th>Nacionalidad</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-body">
              <tr>
                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px">
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

<!-- MODAL INSCRIBIR -->
<div class="modal-overlay" id="modal-inscribir">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Inscribir Atleta</span>
      <button class="modal-close" id="close-inscribir">&times;</button>
    </div>
    <div class="modal-body">
      <div id="error-inscribir" class="alert alert-error" style="display:none"></div>
      <div class="form-row">
        <div class="form-group">
          <label for="campo-nombre">Nombre *</label>
          <input type="text" id="campo-nombre" maxlength="100">
        </div>
        <div class="form-group">
          <label for="campo-apellido">Apellido *</label>
          <input type="text" id="campo-apellido" maxlength="100">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="campo-fecha-nac">Fecha nacimiento *</label>
          <input type="date" id="campo-fecha-nac">
        </div>
        <div class="form-group">
          <label for="campo-nac">Nacionalidad (ISO)</label>
          <input type="text" id="campo-nac" placeholder="ESP" maxlength="3" style="text-transform:uppercase">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="campo-competicion">Competicion *</label>
          <select id="campo-competicion">
            <option value="">Selecciona...</option>
          </select>
        </div>
        <div class="form-group">
          <label for="campo-categoria">Categoria</label>
          <select id="campo-categoria">
            <option value="">Sin categoria</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="campo-dorsal">Dorsal</label>
          <input type="number" id="campo-dorsal" min="1">
        </div>
        <div class="form-group">
          <label for="campo-peso">Peso (kg)</label>
          <input type="number" id="campo-peso" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label for="campo-estatura">Estatura (m)</label>
          <input type="number" id="campo-estatura" step="0.01" min="0">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="btn-cancelar-inscribir">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar-inscribir">
        <span id="btn-inscribir-text">Inscribir</span>
        <span id="btn-inscribir-spinner" class="spinner" style="display:none"></span>
      </button>
    </div>
  </div>
</div>

<!-- MODAL HISTORIAL -->
<div class="modal-overlay" id="modal-historial">
  <div class="modal" style="max-width:800px">
    <div class="modal-header">
      <span class="modal-title" id="historial-titulo">Historial</span>
      <button class="modal-close" id="close-historial">&times;</button>
    </div>
    <div class="modal-body" style="padding:0">
      <div class="table-wrapper" style="border:none">
        <table>
          <thead>
            <tr>
              <th>Evento</th>
              <th>Fecha</th>
              <th>Categoria</th>
              <th>Dorsal</th>
              <th>Peso</th>
              <th>Puesto</th>
            </tr>
          </thead>
          <tbody id="historial-body">
            <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted)">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="btn-cerrar-historial">Cerrar</button>
    </div>
  </div>
</div>

<script src="/js/api.js"></script>
<script src="/js/atletas.js"></script>
</body>
</html>
