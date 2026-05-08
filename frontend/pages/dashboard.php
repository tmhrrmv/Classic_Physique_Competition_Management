<?php
// ============================================================
// pages/dashboard.php — Panel principal
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Datos hardcodeados de ejemplo
// v1.1 - Eliminados datos de ejemplo
//        Stats y competiciones cargan desde la API
//        Corregida ruta del footer
// ============================================================
$pageTitle = 'Dashboard';
$current   = 'dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth();
$token = $current_user['token'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Resumen general de la temporada</p>
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:2rem" id="stats-grid">
  <div class="stat-card"><div class="stat-label">Competiciones</div><div class="stat-value" id="stat-competiciones">—</div></div>
  <div class="stat-card"><div class="stat-label">Atletas</div><div class="stat-value" id="stat-atletas">—</div></div>
  <div class="stat-card"><div class="stat-label">Abiertas</div><div class="stat-value" id="stat-abiertas">—</div></div>
  <div class="stat-card"><div class="stat-label">Cerradas</div><div class="stat-value" id="stat-cerradas">—</div></div>
</div>

<div class="card">
  <div class="card-body">
    <h2 style="font-size:1.5rem;margin-bottom:1rem">Proximas competiciones</h2>
    <table class="table">
      <thead>
        <tr><th>Evento</th><th>Fecha</th><th>Lugar</th><th>Estado</th><th>Inscritos</th></tr>
      </thead>
      <tbody id="tabla-competiciones">
        <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const [compsRes, atletasRes] = await Promise.all([
        fetch('/api/competiciones.php?limit=100', { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } }),
        fetch('/api/atletas.php?limit=1', { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } })
      ]);
      const compsData   = await compsRes.json();
      const atletasData = await atletasRes.json();
      const comps       = compsData.data || [];

      document.getElementById('stat-competiciones').textContent = compsData.pagination?.total ?? comps.length;
      document.getElementById('stat-atletas').textContent       = atletasData.pagination?.total ?? '—';
      document.getElementById('stat-abiertas').textContent      = comps.filter(c => c.estado === 'abierta').length;
      document.getElementById('stat-cerradas').textContent      = comps.filter(c => c.estado === 'cerrada').length;

      const tbody  = document.getElementById('tabla-competiciones');
      const recent = comps.slice(0, 5);
      if (!recent.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">No hay competiciones</td></tr>';
        return;
      }
      const badges = { abierta: 'badge-success', en_curso: 'badge-primary', cerrada: 'badge-muted', sin_fecha: 'badge-muted' };
      tbody.innerHTML = recent.map(c => `
        <tr>
          <td style="font-weight:500">${esc(c.nombre_evento)}</td>
          <td>${c.fecha ?? '—'}</td>
          <td>${esc(c.lugar ?? '—')}</td>
          <td><span class="badge ${badges[c.estado] ?? 'badge-muted'}">${c.estado ?? '—'}</span></td>
          <td>${c.total_inscritos ?? 0}</td>
        </tr>`).join('');
    } catch(e) {
      document.getElementById('tabla-competiciones').innerHTML =
        `<tr><td colspan="5" style="text-align:center;color:var(--destructive);padding:2rem">${e.message}</td></tr>`;
    }
  });

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>