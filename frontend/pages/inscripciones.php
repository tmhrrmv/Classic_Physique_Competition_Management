<?php
$pageTitle = 'Inscripciones';
$current   = 'inscripciones';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador']);
$usuario = htmlspecialchars($current_user['usuario'], ENT_QUOTES, 'UTF-8');
$rol     = htmlspecialchars($current_user['rol'],     ENT_QUOTES, 'UTF-8');
$token   = $current_user['token'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Inscripciones</h1>
    <p>Gestion de inscripciones por competicion</p>
  </div>
  <button class="btn btn-primary" id="btn-nueva-inscripcion">+ Nueva inscripcion</button>
</div>

<!-- Filtro por competicion -->
<div style="margin-bottom:1.5rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
  <select class="input" id="filtro-competicion" style="max-width:320px">
    <option value="">Todas las competiciones</option>
  </select>
  <input class="input" id="filtro-buscar" placeholder="Buscar atleta..." style="max-width:240px">
</div>

<div class="card">
  <div class="card-body">
    <table class="table">
      <thead>
        <tr>
          <th>Dorsal</th>
          <th>Atleta</th>
          <th>Competicion</th>
          <th>Categoria</th>
          <th>Peso</th>
          <th>Estatura</th>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody id="tabla-inscripciones">
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>
      </tbody>
    </table>
    <div id="paginacion" style="display:flex;gap:.5rem;justify-content:center;margin-top:1.25rem"></div>
  </div>
</div>

<!-- Modal nueva inscripcion -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:.75rem;width:100%;max-width:560px;max-height:90vh;overflow-y:auto">
    <div style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 style="font-size:1.5rem">Nueva Inscripcion</h2>
      <button id="btn-cerrar-modal" style="background:none;border:none;color:var(--muted-foreground);font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div style="padding:1.5rem">
      <div id="modal-error" style="display:none;margin-bottom:1rem" class="alert alert-error"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div>
          <label class="form-label">Nombre *</label>
          <input class="input" id="f-nombre" placeholder="Carlos">
        </div>
        <div>
          <label class="form-label">Apellido *</label>
          <input class="input" id="f-apellido" placeholder="Mendoza">
        </div>
        <div>
          <label class="form-label">Fecha nacimiento *</label>
          <input class="input" type="date" id="f-fecha-nac">
        </div>
        <div>
          <label class="form-label">Nacionalidad (ISO)</label>
          <input class="input" id="f-nacionalidad" placeholder="ESP" maxlength="3" style="text-transform:uppercase">
        </div>
        <div>
          <label class="form-label">Competicion *</label>
          <select class="input" id="f-competicion"><option value="">Selecciona...</option></select>
        </div>
        <div>
          <label class="form-label">Categoria</label>
          <select class="input" id="f-categoria"><option value="">Sin categoria</option></select>
        </div>
        <div>
          <label class="form-label">Dorsal</label>
          <input class="input" type="number" id="f-dorsal" min="1">
        </div>
        <div>
          <label class="form-label">Peso (kg)</label>
          <input class="input" type="number" id="f-peso" step="0.01" min="0">
        </div>
        <div>
          <label class="form-label">Estatura (m)</label>
          <input class="input" type="number" id="f-estatura" step="0.01" min="0">
        </div>
      </div>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.75rem">
      <button class="btn btn-outline" id="btn-cancelar">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar">
        <span id="btn-guardar-text">Inscribir</span>
        <span id="btn-guardar-spinner" style="display:none">...</span>
      </button>
    </div>
  </div>
</div>

<style>
  .form-label { display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground); }
  .alert-error { background:color-mix(in oklab,var(--destructive) 15%,transparent);color:var(--destructive);border:1px solid color-mix(in oklab,var(--destructive) 30%,transparent);padding:.75rem 1rem;border-radius:.375rem;font-size:.85rem; }
  .page-btn { width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:.375rem;border:1px solid var(--border);background:transparent;color:var(--muted-foreground);cursor:pointer; }
  .page-btn.active { background:var(--primary);color:var(--primary-foreground);border-color:var(--primary); }
  .page-btn:disabled { opacity:.3;cursor:not-allowed; }
</style>

<script>
  if (!sessionStorage.getItem('token')) sessionStorage.setItem('token', <?= json_encode($token) ?>);

  let paginaActual = 1;
  const LIMITE = 10;

  document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([cargarInscripciones(), cargarSelectores()]);

    document.getElementById('btn-nueva-inscripcion').onclick = abrirModal;
    document.getElementById('btn-cerrar-modal').onclick = cerrarModal;
    document.getElementById('btn-cancelar').onclick = cerrarModal;
    document.getElementById('btn-guardar').onclick = guardar;
    document.getElementById('filtro-competicion').onchange = () => { paginaActual = 1; cargarInscripciones(); };
    document.getElementById('filtro-buscar').oninput = () => { paginaActual = 1; cargarInscripciones(); };
    document.getElementById('modal-overlay').onclick = (e) => { if (e.target === document.getElementById('modal-overlay')) cerrarModal(); };
  });

  async function cargarInscripciones() {
    const tbody = document.getElementById('tabla-inscripciones');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>';
    try {
      const params = new URLSearchParams({ page: paginaActual, limit: LIMITE });
      const compId = document.getElementById('filtro-competicion').value;
      if (compId) params.append('id_competicion', compId);
      const res  = await fetch('/api/atletas.php?' + params, { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } });
      const data = await res.json();
      const items = data.data || [];
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted-foreground)">No hay inscripciones</td></tr>';
        return;
      }
      tbody.innerHTML = items.map(a => `
        <tr>
          <td style="font-family:monospace;color:var(--muted-foreground)">${a.numero_dorsal ?? '—'}</td>
          <td style="font-weight:500">${esc(a.nombre + ' ' + a.apellido)}</td>
          <td>${esc(a.nombre_evento ?? '—')}</td>
          <td>${esc(a.categoria ?? '—')}</td>
          <td>${a.peso_registro ? a.peso_registro + ' kg' : '—'}</td>
          <td>${a.estatura_registro ? a.estatura_registro + ' m' : '—'}</td>
          <td style="font-size:.8rem;color:var(--muted-foreground)">${a.fecha_inscripcion ? a.fecha_inscripcion.split('T')[0] : '—'}</td>
        </tr>`).join('');
      renderPaginacion(data.pagination || {});
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--destructive);padding:2rem">${e.message}</td></tr>`;
    }
  }

  async function cargarSelectores() {
    try {
      const [compsRes, catsRes] = await Promise.all([
        fetch('/api/competiciones.php?limit=100', { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } }),
        fetch('/api/categorias.php', { headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') } }).catch(() => ({ json: () => ({ data: [] }) }))
      ]);
      const comps = (await compsRes.json()).data || [];
      const cats  = (await catsRes.json()).data || [];

      const filtro = document.getElementById('filtro-competicion');
      const fComp  = document.getElementById('f-competicion');
      comps.forEach(c => {
        filtro.innerHTML += `<option value="${c.id_competicion}">${esc(c.nombre_evento)}</option>`;
        fComp.innerHTML  += `<option value="${c.id_competicion}">${esc(c.nombre_evento)}</option>`;
      });

      const fCat = document.getElementById('f-categoria');
      cats.forEach(c => { fCat.innerHTML += `<option value="${c.id_categoria}">${esc(c.nombre)}</option>`; });
    } catch(e) { console.error(e); }
  }

  function renderPaginacion(pag) {
    const el = document.getElementById('paginacion');
    if (!pag.total_pages || pag.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = `<button class="page-btn" ${pag.page<=1?'disabled':''} onclick="irPagina(${pag.page-1})">&#8592;</button>`;
    for (let i = 1; i <= pag.total_pages; i++)
      html += `<button class="page-btn ${i===pag.page?'active':''}" onclick="irPagina(${i})">${i}</button>`;
    html += `<button class="page-btn" ${pag.page>=pag.total_pages?'disabled':''} onclick="irPagina(${pag.page+1})">&#8594;</button>`;
    el.innerHTML = html;
  }

  function irPagina(n) { paginaActual = n; cargarInscripciones(); }

  function abrirModal() {
    document.getElementById('modal-overlay').style.display = 'flex';
    document.getElementById('modal-error').style.display = 'none';
  }

  function cerrarModal() {
    document.getElementById('modal-overlay').style.display = 'none';
  }

  async function guardar() {
    const errEl = document.getElementById('modal-error');
    const data = {
      nombre:            document.getElementById('f-nombre').value.trim(),
      apellido:          document.getElementById('f-apellido').value.trim(),
      fecha_nacimiento:  document.getElementById('f-fecha-nac').value,
      nacionalidad:      document.getElementById('f-nacionalidad').value.trim().toUpperCase() || null,
      id_competicion:    parseInt(document.getElementById('f-competicion').value) || null,
      id_categoria:      parseInt(document.getElementById('f-categoria').value) || null,
      numero_dorsal:     parseInt(document.getElementById('f-dorsal').value) || null,
      peso_registro:     parseFloat(document.getElementById('f-peso').value) || null,
      estatura_registro: parseFloat(document.getElementById('f-estatura').value) || null,
    };

    if (!data.nombre || !data.apellido || !data.fecha_nacimiento || !data.id_competicion) {
      errEl.textContent = 'Nombre, apellido, fecha de nacimiento y competicion son obligatorios';
      errEl.style.display = 'block';
      return;
    }

    document.getElementById('btn-guardar').disabled = true;
    document.getElementById('btn-guardar-text').style.display = 'none';
    document.getElementById('btn-guardar-spinner').style.display = 'inline';

    try {
      const res  = await fetch('/api/atletas.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + sessionStorage.getItem('token') }, body: JSON.stringify(data) });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error al inscribir');
      cerrarModal();
      cargarInscripciones();
    } catch(e) {
      errEl.textContent = e.message;
      errEl.style.display = 'block';
      document.getElementById('btn-guardar').disabled = false;
      document.getElementById('btn-guardar-text').style.display = 'inline';
      document.getElementById('btn-guardar-spinner').style.display = 'none';
    }
  }

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
