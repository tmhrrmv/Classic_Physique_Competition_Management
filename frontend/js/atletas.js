// ============================================================
// js/atletas.js — Gestión de atletas e inscripciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Listar atletas, inscribir, historial,
//        desactivar/reactivar, paginación
//        Mismas mejoras que competiciones.js:
//        const, DOM object, renderTabla separado,
//        toast, delegación de eventos, errores de red
// ============================================================

const LIMITE_POR_PAGINA = 10;
let paginaActual = 1;

const DOM = {
    tablaBody:      () => document.getElementById('tabla-body'),
    paginacion:     () => document.getElementById('paginacion'),
    filtroActivo:   () => document.getElementById('filtro-activo'),
    btnInscribir:   () => document.getElementById('btn-inscribir'),
    toast:          () => document.getElementById('toast'),

    // Modal inscribir
    modalInscribir: () => document.getElementById('modal-inscribir'),
    closeInscribir: () => document.getElementById('close-inscribir'),
    errorInscribir: () => document.getElementById('error-inscribir'),
    btnCancelar:    () => document.getElementById('btn-cancelar-inscribir'),
    btnGuardar:     () => document.getElementById('btn-guardar-inscribir'),
    btnText:        () => document.getElementById('btn-inscribir-text'),
    btnSpinner:     () => document.getElementById('btn-inscribir-spinner'),
    campoNombre:    () => document.getElementById('campo-nombre'),
    campoApellido:  () => document.getElementById('campo-apellido'),
    campoFechaNac:  () => document.getElementById('campo-fecha-nac'),
    campoNac:       () => document.getElementById('campo-nac'),
    campoComp:      () => document.getElementById('campo-competicion'),
    campoCat:       () => document.getElementById('campo-categoria'),
    campoDorsal:    () => document.getElementById('campo-dorsal'),
    campoPeso:      () => document.getElementById('campo-peso'),
    campoEstatura:  () => document.getElementById('campo-estatura'),

    // Modal historial
    modalHistorial: () => document.getElementById('modal-historial'),
    historialTitulo:() => document.getElementById('historial-titulo'),
    historialBody:  () => document.getElementById('historial-body'),
    closeHistorial: () => document.getElementById('close-historial'),
    btnCerrarHist:  () => document.getElementById('btn-cerrar-historial'),
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!sessionStorage.getItem('token') && window.__JWT) {
        sessionStorage.setItem('token', window.__JWT);
    }

    if (!AuthAPI.isLoggedIn()) {
        window.location.href = '/pages/login.php?reason=session_missing';
        return;
    }

    cargarAtletas();
    cargarSelectores();

    DOM.filtroActivo().addEventListener('change', () => {
        paginaActual = 1;
        cargarAtletas();
    });

    DOM.btnInscribir().addEventListener('click', () => abrirModalInscribir());
    DOM.closeInscribir().addEventListener('click', cerrarModalInscribir);
    DOM.btnCancelar().addEventListener('click', cerrarModalInscribir);
    DOM.btnGuardar().addEventListener('click', inscribir);
    DOM.closeHistorial().addEventListener('click', cerrarModalHistorial);
    DOM.btnCerrarHist().addEventListener('click', cerrarModalHistorial);

    DOM.modalInscribir().addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); inscribir(); }
        if (e.key === 'Escape') cerrarModalInscribir();
    });

    DOM.modalInscribir().addEventListener('click', (e) => {
        if (e.target === DOM.modalInscribir()) cerrarModalInscribir();
    });

    DOM.modalHistorial().addEventListener('click', (e) => {
        if (e.target === DOM.modalHistorial()) cerrarModalHistorial();
    });

    // Delegación de eventos en tabla
    DOM.tablaBody().addEventListener('click', (e) => {
        const btnHistorial  = e.target.closest('[data-action="historial"]');
        const btnDesactivar = e.target.closest('[data-action="desactivar"]');
        const btnReactivar  = e.target.closest('[data-action="reactivar"]');

        if (btnHistorial)  verHistorial(btnHistorial.dataset.id, btnHistorial.dataset.nombre);
        if (btnDesactivar) desactivarAtleta(btnDesactivar.dataset.id, btnDesactivar.dataset.nombre);
        if (btnReactivar)  reactivarAtleta(btnReactivar.dataset.id, btnReactivar.dataset.nombre);
    });
});

// -------------------------------------------------------
// cargarAtletas()
// -------------------------------------------------------
async function cargarAtletas() {
    DOM.tablaBody().innerHTML = `
        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px">
            Cargando...
        </td></tr>`;

    const activo = DOM.filtroActivo().value;

    try {
        const data    = await AtletasAPI.getAll({ page: paginaActual, limit: LIMITE_POR_PAGINA, activo });
        const atletas = data.data || [];
        const pag     = data.pagination || {};

        renderTabla(atletas, activo);
        renderPaginacion(pag);

    } catch (err) {
        const msg = err.message.includes('fetch') ? 'Error de conexion con el servidor' : err.message;
        DOM.tablaBody().innerHTML = `
            <tr><td colspan="6" style="text-align:center; color:var(--error); padding:32px">
                ${escapeHtml(msg)}
            </td></tr>`;
    }
}

// -------------------------------------------------------
// renderTabla()
// -------------------------------------------------------
function renderTabla(atletas, activo) {
    if (atletas.length === 0) {
        DOM.tablaBody().innerHTML = `
            <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px">
                No hay atletas
            </td></tr>`;
        return;
    }

    DOM.tablaBody().innerHTML = atletas.map(a => `
        <tr>
          <td style="color:var(--text-muted)">#${a.id_atleta}</td>
          <td><strong>${escapeHtml(a.nombre + ' ' + a.apellido)}</strong></td>
          <td>${formatFecha(a.fecha_nacimiento)}</td>
          <td>${escapeHtml(a.nacionalidad || '—')}</td>
          <td>
            <span class="badge ${a.activo == 1 ? 'badge-success' : 'badge-error'}">
              ${a.activo == 1 ? 'Activo' : 'Inactivo'}
            </span>
          </td>
          <td>
            <div style="display:flex; gap:8px; flex-wrap:wrap">
              <button class="btn btn-secondary btn-sm"
                data-action="historial"
                data-id="${a.id_atleta}"
                data-nombre="${escapeHtml(a.nombre + ' ' + a.apellido)}">
                Historial
              </button>
              ${a.activo == 1
                ? `<button class="btn btn-danger btn-sm"
                    data-action="desactivar"
                    data-id="${a.id_atleta}"
                    data-nombre="${escapeHtml(a.nombre + ' ' + a.apellido)}">
                    Desactivar
                   </button>`
                : `<button class="btn btn-secondary btn-sm"
                    data-action="reactivar"
                    data-id="${a.id_atleta}"
                    data-nombre="${escapeHtml(a.nombre + ' ' + a.apellido)}">
                    Reactivar
                   </button>`
              }
            </div>
          </td>
        </tr>
    `).join('');
}

// -------------------------------------------------------
// renderPaginacion()
// -------------------------------------------------------
function renderPaginacion(pag) {
    if (!pag.total_pages || pag.total_pages <= 1) {
        DOM.paginacion().innerHTML = '';
        return;
    }
    let html = `<button class="page-btn" ${pag.page <= 1 ? 'disabled' : ''} onclick="irPagina(${pag.page - 1})">&#8592;</button>`;
    for (let i = 1; i <= pag.total_pages; i++) {
        html += `<button class="page-btn ${i === pag.page ? 'active' : ''}" onclick="irPagina(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" ${pag.page >= pag.total_pages ? 'disabled' : ''} onclick="irPagina(${pag.page + 1})">&#8594;</button>`;
    DOM.paginacion().innerHTML = html;
}

function irPagina(n) { paginaActual = n; cargarAtletas(); }

// -------------------------------------------------------
// cargarSelectores() — competiciones y categorías
// -------------------------------------------------------
async function cargarSelectores() {
    try {
        const [compsData, catsData] = await Promise.all([
            CompeticionesAPI.getAll({ limit: 100 }),
            fetch('/api/categorias.php', {
                headers: { 'Authorization': `Bearer ${sessionStorage.getItem('token')}` }
            }).then(r => r.json()).catch(() => ({ data: [] }))
        ]);

        const comps = (compsData.data || []).filter(c => c.estado === 'abierta' || c.estado === 'en_curso');
        DOM.campoComp().innerHTML = '<option value="">Selecciona...</option>' +
            comps.map(c => `<option value="${c.id_competicion}">${escapeHtml(c.nombre_evento)}</option>`).join('');

        const cats = catsData.data || catsData || [];
        DOM.campoCat().innerHTML = '<option value="">Sin categoria</option>' +
            cats.map(c => `<option value="${c.id_categoria}">${escapeHtml(c.nombre)}</option>`).join('');

    } catch (err) {
        console.error('Error cargando selectores:', err);
    }
}

// -------------------------------------------------------
// Modal inscribir
// -------------------------------------------------------
function abrirModalInscribir() {
    DOM.campoNombre().value   = '';
    DOM.campoApellido().value = '';
    DOM.campoFechaNac().value = '';
    DOM.campoNac().value      = '';
    DOM.campoComp().value     = '';
    DOM.campoCat().value      = '';
    DOM.campoDorsal().value   = '';
    DOM.campoPeso().value     = '';
    DOM.campoEstatura().value = '';
    DOM.errorInscribir().style.display = 'none';
    DOM.modalInscribir().classList.add('open');
    DOM.campoNombre().focus();
}

function cerrarModalInscribir() {
    DOM.modalInscribir().classList.remove('open');
}

async function inscribir() {
    const errEl    = DOM.errorInscribir();
    const nombre   = DOM.campoNombre().value.trim();
    const apellido = DOM.campoApellido().value.trim();
    const fechaNac = DOM.campoFechaNac().value;
    const nac      = DOM.campoNac().value.trim().toUpperCase();
    const compId   = DOM.campoComp().value;
    const catId    = DOM.campoCat().value;
    const dorsal   = DOM.campoDorsal().value;
    const peso     = DOM.campoPeso().value;
    const estatura = DOM.campoEstatura().value;

    const errors = [];
    if (!nombre)   errors.push('Nombre es obligatorio');
    if (!apellido) errors.push('Apellido es obligatorio');
    if (!fechaNac) errors.push('Fecha de nacimiento es obligatoria');
    if (!compId)   errors.push('Debes seleccionar una competicion');

    if (errors.length > 0) {
        errEl.innerHTML = errors.map(e => `<div>${e}</div>`).join('');
        errEl.style.display = 'flex';
        return;
    }

    setInscribiendo(true);
    errEl.style.display = 'none';

    try {
        await AtletasAPI.inscribir({
            nombre, apellido,
            fecha_nacimiento:  fechaNac,
            nacionalidad:      nac || null,
            id_competicion:    parseInt(compId),
            id_categoria:      catId    ? parseInt(catId)    : null,
            numero_dorsal:     dorsal   ? parseInt(dorsal)   : null,
            peso_registro:     peso     ? parseFloat(peso)   : null,
            estatura_registro: estatura ? parseFloat(estatura) : null,
        });

        cerrarModalInscribir();
        showToast('Atleta inscrito correctamente', 'success');
        cargarAtletas();

    } catch (err) {
        const msg = err.message.includes('fetch') ? 'Error de conexion con el servidor' : err.message;
        errEl.textContent = msg;
        errEl.style.display = 'flex';
        setInscribiendo(false);
    }
}

function setInscribiendo(loading) {
    DOM.btnGuardar().disabled = loading;
    DOM.btnText().style.display    = loading ? 'none'        : 'inline';
    DOM.btnSpinner().style.display = loading ? 'inline-block': 'none';
}

// -------------------------------------------------------
// Historial
// -------------------------------------------------------
async function verHistorial(id, nombre) {
    DOM.historialTitulo().textContent = `Historial — ${nombre}`;
    DOM.historialBody().innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted)">Cargando...</td></tr>`;
    DOM.modalHistorial().classList.add('open');

    try {
        const data     = await AtletasAPI.getHistorial(id);
        const eventos  = data.data || [];

        if (eventos.length === 0) {
            DOM.historialBody().innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted)">Sin historial</td></tr>`;
            return;
        }

        DOM.historialBody().innerHTML = eventos.map(e => `
            <tr>
              <td>${escapeHtml(e.nombre_evento)}</td>
              <td>${e.fecha_evento ? formatFecha(e.fecha_evento) : '—'}</td>
              <td>${escapeHtml(e.categoria || '—')}</td>
              <td>${e.numero_dorsal ?? '—'}</td>
              <td>${e.peso_registro ? e.peso_registro + ' kg' : '—'}</td>
              <td>${e.ranking_final
                ? `<span class="badge badge-success">#${e.ranking_final}</span>`
                : '<span class="badge badge-default">Sin resultado</span>'}</td>
            </tr>
        `).join('');

    } catch (err) {
        DOM.historialBody().innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--error); padding:32px">${escapeHtml(err.message)}</td></tr>`;
    }
}

function cerrarModalHistorial() {
    DOM.modalHistorial().classList.remove('open');
}

// -------------------------------------------------------
// Desactivar / Reactivar
// -------------------------------------------------------
async function desactivarAtleta(id, nombre) {
    if (!confirm(`Desactivar a "${nombre}"?\nNo podra inscribirse en nuevos eventos.`)) return;
    try {
        await AtletasAPI.desactivar(id);
        showToast('Atleta desactivado', 'success');
        cargarAtletas();
    } catch (err) {
        showToast(err.message.includes('fetch') ? 'Error de conexion' : err.message, 'error');
    }
}

async function reactivarAtleta(id, nombre) {
    if (!confirm(`Reactivar a "${nombre}"?`)) return;
    try {
        await AtletasAPI.reactivar(id);
        showToast('Atleta reactivado', 'success');
        cargarAtletas();
    } catch (err) {
        showToast(err.message.includes('fetch') ? 'Error de conexion' : err.message, 'error');
    }
}

// -------------------------------------------------------
// Toast
// -------------------------------------------------------
function showToast(msg, tipo = 'success') {
    const toast = DOM.toast();
    toast.textContent = msg;
    toast.className   = `toast toast-${tipo} show`;
    setTimeout(() => toast.classList.remove('show'), 3500);
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatFecha(fecha) {
    const d = new Date(fecha);
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
}
