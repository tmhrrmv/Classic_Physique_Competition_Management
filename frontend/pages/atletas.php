<?php
// ============================================================
// pages/atletas.php — Gestión de atletas
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Datos hardcodeados de ejemplo
// v1.1 - Eliminados datos de ejemplo
//        Tabla carga datos reales via API (atletas.js)
//        Corregida ruta del footer
// ============================================================
$pageTitle = 'Atletas';
$current   = 'atletas';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador']);
$token = $current_user['token'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Atletas</h1>
    <p>Registro de competidores</p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <input class="input" id="filtro-buscar" placeholder="Buscar por nombre..." style="margin-bottom:1rem;max-width:320px">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>Nombre</th><th>Nacionalidad</th><th>Estado</th></tr>
      </thead>
      <tbody id="tabla-atletas">
        <tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>
      </tbody>
    </table>
    <div id="paginacion" style="display:flex;gap:.5rem;justify-content:center;margin-top:1.25rem"></div>
  </div>
</div>

<script>
  let paginaActual = 1;
  const LIMITE = 10;

  document.addEventListener('DOMContentLoaded', () => {
    cargarAtletas();
    document.getElementById('filtro-buscar').oninput = () => { paginaActual = 1; cargarAtletas(); };
  });

  async function cargarAtletas() {
    const tbody  = document.getElementById('tabla-atletas');
    const buscar = document.getElementById('filtro-buscar').value;
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>';
    try {
      const params = new URLSearchParams({ page: paginaActual, limit: LIMITE });
      if (buscar) params.append('buscar', buscar);
      const res  = await fetch('/api/atletas.php?' + params, { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } });
      const data = await res.json();
      const items = data.data || [];
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted-foreground)">No hay atletas</td></tr>';
        return;
      }
      tbody.innerHTML = items.map(a => `
        <tr>
          <td style="font-family:monospace;color:var(--muted-foreground)">#${a.id_atleta}</td>
          <td style="font-weight:500">${esc(a.nombre + ' ' + a.apellido)}</td>
          <td>${esc(a.nacionalidad ?? '—')}</td>
          <td><span class="badge ${a.activo ? 'badge-success' : 'badge-muted'}">${a.activo ? 'Activo' : 'Inactivo'}</span></td>
        </tr>`).join('');
      renderPaginacion(data.pagination || {});
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--destructive);padding:2rem">${e.message}</td></tr>`;
    }
  }

  function renderPaginacion(pag) {
    const el = document.getElementById('paginacion');
    if (!pag.total_pages || pag.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = `<button style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:.375rem;background:transparent;color:var(--foreground);cursor:pointer" ${pag.page<=1?'disabled':''} onclick="irPagina(${pag.page-1})">&#8592;</button>`;
    for (let i = 1; i <= pag.total_pages; i++)
      html += `<button style="padding:.4rem .75rem;border:1px solid ${i===pag.page?'var(--primary)':'var(--border)'};border-radius:.375rem;background:${i===pag.page?'var(--primary)':'transparent'};color:${i===pag.page?'var(--primary-foreground)':'var(--foreground)'};cursor:pointer" onclick="irPagina(${i})">${i}</button>`;
    html += `<button style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:.375rem;background:transparent;color:var(--foreground);cursor:pointer" ${pag.page>=pag.total_pages?'disabled':''} onclick="irPagina(${pag.page+1})">&#8594;</button>`;
    el.innerHTML = html;
  }

  function irPagina(n) { paginaActual = n; cargarAtletas(); }

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>