<?php
// ============================================================
// pages/competiciones.php — Gestión de competiciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Datos hardcodeados de ejemplo
// v1.1 - Eliminados datos de ejemplo
//        Tabla carga datos reales via API
//        Corregida ruta del footer
// ============================================================
$pageTitle = 'Competiciones';
$current   = 'competiciones';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador']);
$token = $current_user['token'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Competiciones</h1>
    <p>Gestion de eventos de Classic Physique</p>
  </div>
  <button class="btn btn-primary" id="btn-nueva">+ Nueva competicion</button>
</div>

<div style="margin-bottom:1.5rem;display:flex;gap:1rem;flex-wrap:wrap">
  <select class="input" id="filtro-estado" style="max-width:220px">
    <option value="">Todos los estados</option>
    <option value="abierta">Abierta</option>
    <option value="en_curso">En curso</option>
    <option value="cerrada">Cerrada</option>
    <option value="sin_fecha">Sin fecha</option>
  </select>
</div>

<div class="card">
  <div class="card-body">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>Evento</th><th>Fecha</th><th>Lugar</th><th>Estado</th><th>Inscritos</th><th></th></tr>
      </thead>
      <tbody id="tabla-body">
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>
      </tbody>
    </table>
    <div id="paginacion" style="display:flex;gap:.5rem;justify-content:center;margin-top:1.25rem"></div>
  </div>
</div>

<!-- Modal -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:.75rem;width:100%;max-width:480px">
    <div style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 id="modal-titulo" style="font-size:1.5rem">Nueva Competicion</h2>
      <button id="btn-cerrar" style="background:none;border:none;color:var(--muted-foreground);font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div style="padding:1.5rem">
      <div id="modal-error" style="display:none;margin-bottom:1rem;background:color-mix(in oklab,var(--destructive) 15%,transparent);color:var(--destructive);border:1px solid color-mix(in oklab,var(--destructive) 30%,transparent);padding:.75rem 1rem;border-radius:.375rem;font-size:.85rem"></div>
      <input type="hidden" id="edit-id">
      <div style="margin-bottom:1rem">
        <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Nombre del evento *</label>
        <input class="input" id="campo-nombre" placeholder="Torneo Apertura 2025">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Fecha</label>
          <input class="input" type="date" id="campo-fecha">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Lugar</label>
          <input class="input" id="campo-lugar" placeholder="Madrid">
        </div>
      </div>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.75rem">
      <button class="btn btn-outline" id="btn-cancelar">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar">
        <span id="btn-text">Guardar</span>
      </button>
    </div>
  </div>
</div>

<script>
  let paginaActual = 1;
  const LIMITE = 10;

  document.addEventListener('DOMContentLoaded', () => {
    cargarCompeticiones();
    document.getElementById('filtro-estado').onchange = () => { paginaActual = 1; cargarCompeticiones(); };
    document.getElementById('btn-nueva').onclick = () => abrirModal();
    document.getElementById('btn-cerrar').onclick = cerrarModal;
    document.getElementById('btn-cancelar').onclick = cerrarModal;
    document.getElementById('btn-guardar').onclick = guardar;
    document.getElementById('modal-overlay').onclick = (e) => { if (e.target === document.getElementById('modal-overlay')) cerrarModal(); };
  });

  async function cargarCompeticiones() {
    const tbody  = document.getElementById('tabla-body');
    const estado = document.getElementById('filtro-estado').value;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>';
    try {
      const params = new URLSearchParams({ page: paginaActual, limit: LIMITE });
      if (estado) params.append('estado', estado);
      const res  = await fetch('/api/competiciones.php?' + params, { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } });
      const data = await res.json();
      const items = data.data || [];
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">No hay competiciones</td></tr>';
        return;
      }
      const badges = { abierta: 'badge-success', en_curso: 'badge-primary', cerrada: 'badge-muted', sin_fecha: 'badge-muted' };
      tbody.innerHTML = items.map(c => `
        <tr>
          <td style="font-family:monospace;color:var(--muted-foreground)">#${c.id_competicion}</td>
          <td style="font-weight:500">${esc(c.nombre_evento)}</td>
          <td>${c.fecha ?? '—'}</td>
          <td>${esc(c.lugar ?? '—')}</td>
          <td><span class="badge ${badges[c.estado] ?? 'badge-muted'}">${c.estado ?? '—'}</span></td>
          <td>${c.total_inscritos ?? 0}</td>
          <td style="display:flex;gap:.5rem">
            <button class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem" data-action="editar" data-id="${c.id_competicion}">Editar</button>
            <button class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem;color:var(--destructive);border-color:var(--destructive)" data-action="eliminar" data-id="${c.id_competicion}" data-nombre="${esc(c.nombre_evento)}">Eliminar</button>
          </td>
        </tr>`).join('');
      renderPaginacion(data.pagination || {});
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--destructive);padding:2rem">${e.message}</td></tr>`;
    }
  }

  document.addEventListener('click', (e) => {
    const btnEditar   = e.target.closest('[data-action="editar"]');
    const btnEliminar = e.target.closest('[data-action="eliminar"]');
    if (btnEditar)   editarComp(btnEditar.dataset.id);
    if (btnEliminar) eliminarComp(btnEliminar.dataset.id, btnEliminar.dataset.nombre);
  });

  function renderPaginacion(pag) {
    const el = document.getElementById('paginacion');
    if (!pag.total_pages || pag.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = `<button style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:.375rem;background:transparent;color:var(--foreground);cursor:pointer" ${pag.page<=1?'disabled':''} onclick="irPagina(${pag.page-1})">&#8592;</button>`;
    for (let i = 1; i <= pag.total_pages; i++)
      html += `<button style="padding:.4rem .75rem;border:1px solid ${i===pag.page?'var(--primary)':'var(--border)'};border-radius:.375rem;background:${i===pag.page?'var(--primary)':'transparent'};color:${i===pag.page?'var(--primary-foreground)':'var(--foreground)'};cursor:pointer" onclick="irPagina(${i})">${i}</button>`;
    html += `<button style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:.375rem;background:transparent;color:var(--foreground);cursor:pointer" ${pag.page>=pag.total_pages?'disabled':''} onclick="irPagina(${pag.page+1})">&#8594;</button>`;
    el.innerHTML = html;
  }

  function irPagina(n) { paginaActual = n; cargarCompeticiones(); }

  function abrirModal(comp = null) {
    document.getElementById('modal-titulo').textContent = comp ? 'Editar Competicion' : 'Nueva Competicion';
    document.getElementById('edit-id').value      = comp?.id_competicion ?? '';
    document.getElementById('campo-nombre').value = comp?.nombre_evento  ?? '';
    document.getElementById('campo-fecha').value  = comp?.fecha          ?? '';
    document.getElementById('campo-lugar').value  = comp?.lugar          ?? '';
    document.getElementById('btn-text').textContent = comp ? 'Actualizar' : 'Crear';
    document.getElementById('modal-error').style.display = 'none';
    document.getElementById('modal-overlay').style.display = 'flex';
  }

  function cerrarModal() { document.getElementById('modal-overlay').style.display = 'none'; }

  async function editarComp(id) {
    try {
      const res  = await fetch(`/api/competiciones.php?id=${id}`, { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } });
      const data = await res.json();
      abrirModal(data);
    } catch(e) { alert(e.message); }
  }

  async function guardar() {
    const id     = document.getElementById('edit-id').value;
    const nombre = document.getElementById('campo-nombre').value.trim();
    const fecha  = document.getElementById('campo-fecha').value;
    const lugar  = document.getElementById('campo-lugar').value.trim();
    const errEl  = document.getElementById('modal-error');
    if (!nombre) { errEl.textContent = 'El nombre es obligatorio'; errEl.style.display = 'block'; return; }
    try {
      const method = id ? 'PATCH' : 'POST';
      const url    = id ? `/api/competiciones.php?id=${id}` : '/api/competiciones.php';
      const res    = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + sessionStorage.getItem('token') }, body: JSON.stringify({ nombre_evento: nombre, fecha: fecha || null, lugar: lugar || null }) });
      const json   = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error');
      cerrarModal();
      cargarCompeticiones();
    } catch(e) { errEl.textContent = e.message; errEl.style.display = 'block'; }
  }

  async function eliminarComp(id, nombre) {
    if (!confirm(`Eliminar "${nombre}"?`)) return;
    try {
      const res  = await fetch(`/api/competiciones.php?id=${id}`, { method: 'DELETE', headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error');
      cargarCompeticiones();
    } catch(e) { alert(e.message); }
  }

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>