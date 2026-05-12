<?php
// ============================================================
// pages/puntuaciones.php — Registro de puntuaciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Página inicial
// v1.1 - Fix: eliminada llamada a /api/jueces.php (no existe)
//        Los jueces se cargan desde /api/competiciones.php
//        con los datos de inscripcion de la competicion
//        Jueces cargados directamente desde la BD via endpoint
//        nuevo en atletas.php con ?jueces=1
// ============================================================
$pageTitle = 'Puntuaciones';
$current   = 'puntuaciones';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador', 'juez']);
$rol   = htmlspecialchars($current_user['rol'], ENT_QUOTES, 'UTF-8');
$token = $current_user['token'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Puntuaciones</h1>
    <p>Registro de puntuaciones por juez y competicion</p>
  </div>
  <?php if (in_array($rol, ['admin', 'organizador', 'juez'])): ?>
  <button class="btn btn-primary" id="btn-nueva-punt">+ Registrar puntuacion</button>
  <?php endif; ?>
</div>

<div style="margin-bottom:1.5rem">
  <select class="input" id="filtro-competicion" style="max-width:320px">
    <option value="">Selecciona una competicion...</option>
  </select>
</div>

<div class="card">
  <div class="card-body">
    <table class="table">
      <thead>
        <tr>
          <th>Atleta</th>
          <th>Categoria</th>
          <th>Juez</th>
          <th>Ranking</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-puntuaciones">
        <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Selecciona una competicion para ver puntuaciones</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nueva puntuacion -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:1rem">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:.75rem;width:100%;max-width:480px">
    <div style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 style="font-size:1.5rem">Registrar Puntuacion</h2>
      <button id="btn-cerrar" style="background:none;border:none;color:var(--muted-foreground);font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    <div style="padding:1.5rem">
      <div id="modal-error" style="display:none;margin-bottom:1rem;background:color-mix(in oklab,var(--destructive) 15%,transparent);color:var(--destructive);border:1px solid color-mix(in oklab,var(--destructive) 30%,transparent);padding:.75rem 1rem;border-radius:.375rem;font-size:.85rem"></div>
      <div style="display:grid;gap:1rem">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Atleta inscrito *</label>
          <select class="input" id="f-inscripcion"><option value="">Selecciona...</option></select>
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Juez *</label>
          <select class="input" id="f-juez"><option value="">Selecciona...</option></select>
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:500;margin-bottom:.4rem;color:var(--muted-foreground)">Ranking otorgado *</label>
          <input class="input" type="number" id="f-ranking" min="1" placeholder="1">
        </div>
      </div>
    </div>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.75rem">
      <button class="btn btn-outline" id="btn-cancelar">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar">Registrar</button>
    </div>
  </div>
</div>

<script>
  if (!sessionStorage.getItem('token')) sessionStorage.setItem('token', <?= json_encode($token) ?>);

  document.addEventListener('DOMContentLoaded', async () => {
    await cargarCompeticiones();
    document.getElementById('filtro-competicion').onchange = cargarPuntuaciones;
    document.getElementById('btn-nueva-punt').onclick  = abrirModal;
    document.getElementById('btn-cerrar').onclick      = cerrarModal;
    document.getElementById('btn-cancelar').onclick    = cerrarModal;
    document.getElementById('btn-guardar').onclick     = guardar;
    document.getElementById('modal-overlay').onclick   = (e) => {
      if (e.target === document.getElementById('modal-overlay')) cerrarModal();
    };
  });

  async function cargarCompeticiones() {
    try {
      const res  = await fetch('/api/competiciones.php?limit=100', {
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const data = await res.json();
      const sel  = document.getElementById('filtro-competicion');
      (data.data || []).forEach(c => {
        sel.innerHTML += `<option value="${c.id_competicion}">${esc(c.nombre_evento)}</option>`;
      });
    } catch(e) { console.error('Error cargando competiciones', e); }
  }

  async function cargarPuntuaciones() {
    const compId = document.getElementById('filtro-competicion').value;
    const tbody  = document.getElementById('tabla-puntuaciones');
    if (!compId) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Selecciona una competicion</td></tr>';
      return;
    }
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">Cargando...</td></tr>';
    try {
      const res  = await fetch(`/api/puntuaciones.php?id_competicion=${compId}`, {
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const data = await res.json();
      const items = data.data || [];
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted-foreground)">No hay puntuaciones registradas</td></tr>';
        return;
      }
      tbody.innerHTML = items.map(p => `
        <tr>
          <td style="font-weight:500">${esc(p.atleta ?? '—')}</td>
          <td>${esc(p.categoria ?? '—')}</td>
          <td>${esc(p.juez ?? '—')}</td>
          <td><span style="font-family:'Bebas Neue';font-size:1.25rem;color:var(--primary)">#${p.ranking_otorgado ?? '—'}</span></td>
          <td>
            <button class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem"
              onclick="anularPuntuacion(${p.id_puntuacion})">Anular</button>
          </td>
        </tr>`).join('');
    } catch(e) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--destructive);padding:2rem">${e.message}</td></tr>`;
    }
  }

  async function abrirModal() {
    const compId = document.getElementById('filtro-competicion').value;
    if (!compId) { alert('Selecciona una competicion primero'); return; }
    await Promise.all([
      cargarInscripcionesModal(compId),
      cargarJuecesModal()
    ]);
    document.getElementById('modal-error').style.display = 'none';
    document.getElementById('f-ranking').value = '';
    document.getElementById('modal-overlay').style.display = 'flex';
  }

  function cerrarModal() {
    document.getElementById('modal-overlay').style.display = 'none';
  }

  async function cargarInscripcionesModal(compId) {
    const sel = document.getElementById('f-inscripcion');
    sel.innerHTML = '<option value="">Cargando...</option>';
    try {
      // Carga atletas inscritos en la competicion via atletas.php
      const res  = await fetch(`/api/atletas.php?id_competicion=${compId}&limit=100`, {
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const data = await res.json();
      sel.innerHTML = '<option value="">Selecciona atleta...</option>';
      (data.data || []).forEach(a => {
        sel.innerHTML += `<option value="${a.id_inscripcion}">${esc(a.nombre + ' ' + a.apellido)}</option>`;
      });
    } catch(e) {
      sel.innerHTML = '<option value="">Error al cargar atletas</option>';
    }
  }

  async function cargarJuecesModal() {
    const sel = document.getElementById('f-juez');
    sel.innerHTML = '<option value="">Cargando...</option>';
    try {
      // Carga jueces activos directamente via puntuaciones.php con ?jueces=1
      const res  = await fetch('/api/puntuaciones.php?jueces=1', {
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const data = await res.json();
      sel.innerHTML = '<option value="">Selecciona juez...</option>';
      (data.data || []).forEach(j => {
        sel.innerHTML += `<option value="${j.id_juez}">${esc(j.nombre)}</option>`;
      });
    } catch(e) {
      sel.innerHTML = '<option value="">Error al cargar jueces</option>';
    }
  }

  async function guardar() {
    const errEl = document.getElementById('modal-error');
    const body  = {
      id_inscripcion: parseInt(document.getElementById('f-inscripcion').value) || null,
      id_juez:        parseInt(document.getElementById('f-juez').value)        || null,
      ranking:        parseInt(document.getElementById('f-ranking').value)     || null,
    };
    if (!body.id_inscripcion || !body.id_juez || !body.ranking) {
      errEl.textContent   = 'Todos los campos son obligatorios';
      errEl.style.display = 'block';
      return;
    }
    try {
      const res  = await fetch('/api/puntuaciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + sessionStorage.getItem('token') },
        body: JSON.stringify(body)
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error al registrar');
      cerrarModal();
      cargarPuntuaciones();
    } catch(e) {
      errEl.textContent   = e.message;
      errEl.style.display = 'block';
    }
  }

  async function anularPuntuacion(id) {
    if (!confirm('Anular esta puntuacion?')) return;
    try {
      const res  = await fetch(`/api/puntuaciones.php?id=${id}`, {
        method: 'DELETE',
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error al anular');
      cargarPuntuaciones();
    } catch(e) { alert(e.message); }
  }

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
