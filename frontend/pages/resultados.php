<?php
// ============================================================
// pages/resultados.php — Podio y clasificacion final
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Página inicial
// v1.1 - Fix: campo ranking_final renombrado a puesto
//        en la respuesta de la API
// ============================================================
$pageTitle = 'Resultados';
$current   = 'resultados';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador', 'juez', 'consulta_publica']);
$rol   = htmlspecialchars($current_user['rol'], ENT_QUOTES, 'UTF-8');
$token = $current_user['token'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Resultados</h1>
    <p>Podio y clasificacion final por competicion</p>
  </div>
  <?php if (in_array($rol, ['admin', 'organizador'])): ?>
  <button class="btn btn-primary" id="btn-calcular">Calcular resultados</button>
  <?php endif; ?>
</div>

<div style="margin-bottom:1.5rem">
  <select class="input" id="filtro-competicion" style="max-width:320px">
    <option value="">Selecciona una competicion...</option>
  </select>
</div>

<div id="resultados-container">
  <div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:var(--muted-foreground)">
      Selecciona una competicion para ver los resultados
    </div>
  </div>
</div>

<script>
  if (!sessionStorage.getItem('token')) sessionStorage.setItem('token', <?= json_encode($token) ?>);

  document.addEventListener('DOMContentLoaded', async () => {
    await cargarCompeticiones();
    document.getElementById('filtro-competicion').onchange = cargarResultados;
    const btnCalc = document.getElementById('btn-calcular');
    if (btnCalc) btnCalc.onclick = calcularResultados;
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

  async function cargarResultados() {
    const compId = document.getElementById('filtro-competicion').value;
    const cont   = document.getElementById('resultados-container');

    if (!compId) {
      cont.innerHTML = '<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:var(--muted-foreground)">Selecciona una competicion</div></div>';
      return;
    }

    cont.innerHTML = '<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:var(--muted-foreground)">Cargando...</div></div>';

    try {
      const res  = await fetch(`/api/resultados.php?id_competicion=${compId}`, {
        headers: { Authorization: 'Bearer ' + sessionStorage.getItem('token') }
      });
      const data = await res.json();

      if (!res.ok) {
        cont.innerHTML = `<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:var(--muted-foreground)">${esc(data.error || 'No hay resultados calculados')}</div></div>`;
        return;
      }

      const items = data.data || [];
      if (!items.length) {
        cont.innerHTML = '<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:var(--muted-foreground)">No hay resultados calculados para esta competicion</div></div>';
        return;
      }

      // Agrupar por categoria
      const grupos = {};
      items.forEach(r => {
        const cat = r.categoria || 'Sin categoria';
        if (!grupos[cat]) grupos[cat] = [];
        grupos[cat].push(r);
      });

      const colorPuesto = { 1: 'var(--primary)', 2: 'oklch(0.8 0.01 80)', 3: 'oklch(0.65 0.08 40)' };

      cont.innerHTML = Object.entries(grupos).map(([cat, rows]) => `
        <div class="card" style="margin-bottom:1.25rem">
          <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.75rem">
            <h2 style="font-size:1.5rem">${esc(cat)}</h2>
            <span style="font-size:.8rem;color:var(--muted-foreground)">${rows.length} atletas</span>
          </div>
          <div class="card-body" style="padding:0">
            <table class="table">
              <thead>
                <tr><th>Puesto</th><th>Atleta</th><th>Nac.</th><th>Media</th><th>Jueces</th></tr>
              </thead>
              <tbody>
                ${rows.map(r => `
                  <tr>
                    <td>
                      <span style="font-family:'Bebas Neue';font-size:${r.puesto <= 3 ? '1.5' : '1.25'}rem;color:${colorPuesto[r.puesto] ?? 'var(--muted-foreground)'}">
                        #${r.puesto}
                      </span>
                    </td>
                    <td style="font-weight:500">${esc(r.atleta)}</td>
                    <td style="color:var(--muted-foreground)">${esc(r.nacionalidad ?? '—')}</td>
                    <td style="font-family:'Bebas Neue';font-size:1.1rem;color:var(--primary)">${r.media_ranking ?? '—'}</td>
                    <td style="color:var(--muted-foreground)">${r.num_jueces ?? '—'}</td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </div>`).join('');

    } catch(e) {
      cont.innerHTML = `<div class="card"><div class="card-body" style="color:var(--destructive);text-align:center;padding:2rem">${e.message}</div></div>`;
    }
  }

  async function calcularResultados() {
    const compId = document.getElementById('filtro-competicion').value;
    if (!compId) { alert('Selecciona una competicion primero'); return; }
    if (!confirm('Calcular resultados para esta competicion?')) return;
    try {
      const res  = await fetch('/api/resultados.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + sessionStorage.getItem('token') },
        body: JSON.stringify({ id_competicion: parseInt(compId) })
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Error al calcular');
      cargarResultados();
    } catch(e) { alert(e.message); }
  }

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
