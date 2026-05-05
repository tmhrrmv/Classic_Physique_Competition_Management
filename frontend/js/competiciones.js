// ============================================================
// js/competiciones.js — Gestión de competiciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Listar, crear, editar y eliminar competiciones
// v1.1 - Paginación, filtro por estado, modal crear/editar
// v1.2 - Fix JS 5:  const en lugar de let para limitePorPagina
//      - Fix JS 6:  elementos DOM agrupados en objeto DOM
//      - Fix JS 7:  renderTabla() separada de cargarCompeticiones()
//      - Fix JS 8:  retroceder página si queda vacía al eliminar
//      - Fix JS 9:  toast de éxito al guardar/eliminar
//      - Fix JS 10: texto botón dinámico Creando/Actualizando
//      - Fix JS 11: Enter en modal para enviar formulario
//      - Fix JS 12: delegación de eventos en lugar de onclick inline
//      - Fix JS 13: distinción errores de red vs errores de API
// ============================================================

// Fix JS 5: const — no se reasigna nunca
const LIMITE_POR_PAGINA = 10;

let paginaActual   = 1;
let totalPaginas   = 1;
let modoEdicion    = false;

// Fix JS 6: elementos DOM agrupados en objeto constante
// Evita múltiples getElementById a lo largo del código
const DOM = {
    tablaBody:      () => document.getElementById('tabla-body'),
    paginacion:     () => document.getElementById('paginacion'),
    filtroEstado:   () => document.getElementById('filtro-estado'),
    btnNueva:       () => document.getElementById('btn-nueva'),
    modalOverlay:   () => document.getElementById('modal-overlay'),
    modalTitulo:    () => document.getElementById('modal-titulo'),
    modalError:     () => document.getElementById('modal-error'),
    modalClose:     () => document.getElementById('modal-close'),
    editId:         () => document.getElementById('edit-id'),
    campoNombre:    () => document.getElementById('campo-nombre'),
    campoFecha:     () => document.getElementById('campo-fecha'),
    campoLugar:     () => document.getElementById('campo-lugar'),
    btnGuardar:     () => document.getElementById('btn-guardar'),
    btnGuardarText: () => document.getElementById('btn-guardar-text'),
    btnGuardarSpinner: () => document.getElementById('btn-guardar-spinner'),
    btnCancelar:    () => document.getElementById('btn-cancelar'),
    toast:          () => document.getElementById('toast'),
};

document.addEventListener('DOMContentLoaded', () => {
    if (!sessionStorage.getItem('token') && window.__JWT) {
        sessionStorage.setItem('token', window.__JWT);
    }

    if (!AuthAPI.isLoggedIn()) {
        window.location.href = '/pages/login.php?reason=session_missing';
        return;
    }

    cargarCompeticiones();

    DOM.filtroEstado().addEventListener('change', () => {
        paginaActual = 1;
        cargarCompeticiones();
    });

    DOM.btnNueva().addEventListener('click', () => abrirModal());
    DOM.modalClose().addEventListener('click', cerrarModal);
    DOM.btnCancelar().addEventListener('click', cerrarModal);
    DOM.btnGuardar().addEventListener('click', guardar);

    // Fix JS 11: Enter en modal para enviar
    DOM.modalOverlay().addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && DOM.modalOverlay().classList.contains('open')) {
            e.preventDefault();
            guardar();
        }
        if (e.key === 'Escape') cerrarModal();
    });

    // Cerrar modal al clicar fuera
    DOM.modalOverlay().addEventListener('click', (e) => {
        if (e.target === DOM.modalOverlay()) cerrarModal();
    });

    // Fix JS 12: delegación de eventos en tabla
    // Sustituye onclick inline — más seguro y eficiente
    DOM.tablaBody().addEventListener('click', (e) => {
        const btnEditar   = e.target.closest('[data-action="editar"]');
        const btnEliminar = e.target.closest('[data-action="eliminar"]');

        if (btnEditar) {
            editarCompeticion(btnEditar.dataset.id);
        }
        if (btnEliminar) {
            eliminarCompeticion(btnEliminar.dataset.id, btnEliminar.dataset.nombre);
        }
    });
});

// -------------------------------------------------------
// cargarCompeticiones()
// -------------------------------------------------------
async function cargarCompeticiones() {
    DOM.tablaBody().innerHTML = `
        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px">
            Cargando...
        </td></tr>`;

    const estado = DOM.filtroEstado().value;

    try {
        const params = { page: paginaActual, limit: LIMITE_POR_PAGINA };
        if (estado) params.estado = estado;

        const data  = await CompeticionesAPI.getAll(params);
        const comps = data.data || [];
        const pag   = data.pagination || {};

        totalPaginas = pag.total_pages || 1;

        // Fix JS 7: renderTabla() separada
        renderTabla(comps);
        renderPaginacion(pag);

    } catch (err) {
        // Fix JS 13: distinción error de red vs error de API
        const msg = err.message.includes('fetch')
            ? 'Error de conexion con el servidor'
            : err.message;
        DOM.tablaBody().innerHTML = `
            <tr><td colspan="7" style="text-align:center; color:var(--error); padding:32px">
                ${escapeHtml(msg)}
            </td></tr>`;
    }
}

// -------------------------------------------------------
// Fix JS 7: renderTabla() separada
// Solo renderiza — no hace fetch ni gestiona estado
// -------------------------------------------------------
function renderTabla(comps) {
    if (comps.length === 0) {
        DOM.tablaBody().innerHTML = `
            <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:32px">
                No hay competiciones
            </td></tr>`;
        DOM.paginacion().innerHTML = '';
        return;
    }

    DOM.tablaBody().innerHTML = comps.map(c => `
        <tr>
          <td style="color:var(--text-muted)">#${c.id_competicion}</td>
          <td><strong>${escapeHtml(c.nombre_evento)}</strong></td>
          <td>${c.fecha ? formatFecha(c.fecha) : '—'}</td>
          <td>${escapeHtml(c.lugar || '—')}</td>
          <td>${UI.estadoBadge(c.estado)}</td>
          <td>${c.total_inscritos ?? 0}</td>
          <td>
            <div style="display:flex; gap:8px">
              <button class="btn btn-secondary btn-sm"
                data-action="editar"
                data-id="${c.id_competicion}">
                Editar
              </button>
              <button class="btn btn-danger btn-sm"
                data-action="eliminar"
                data-id="${c.id_competicion}"
                data-nombre="${escapeHtml(c.nombre_evento)}">
                Eliminar
              </button>
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

function irPagina(n) {
    paginaActual = n;
    cargarCompeticiones();
}

// -------------------------------------------------------
// Modal
// -------------------------------------------------------
function abrirModal(comp = null) {
    modoEdicion = !!comp;
    // Fix JS 10: texto dinámico según modo
    DOM.modalTitulo().textContent   = comp ? 'Editar Competicion' : 'Nueva Competicion';
    DOM.btnGuardarText().textContent= comp ? 'Actualizar'         : 'Crear';

    DOM.editId().value      = comp?.id_competicion ?? '';
    DOM.campoNombre().value = comp?.nombre_evento  ?? '';
    DOM.campoFecha().value  = comp?.fecha          ?? '';
    DOM.campoLugar().value  = comp?.lugar          ?? '';

    DOM.modalError().style.display = 'none';
    DOM.modalOverlay().classList.add('open');
    DOM.campoNombre().focus();
}

function cerrarModal() {
    DOM.modalOverlay().classList.remove('open');
}

async function editarCompeticion(id) {
    try {
        const comp = await CompeticionesAPI.getById(id);
        abrirModal(comp);
    } catch (err) {
        // Fix JS 13: distinción error de red vs API
        const msg = err.message.includes('fetch')
            ? 'Error de conexion al cargar la competicion'
            : err.message;
        showToast(msg, 'error');
    }
}

// -------------------------------------------------------
// guardar() — crear o actualizar
// Fix JS 10: texto dinámico Creando.../Actualizando...
// -------------------------------------------------------
async function guardar() {
    const id     = DOM.editId().value;
    const nombre = DOM.campoNombre().value.trim();
    const fecha  = DOM.campoFecha().value;
    const lugar  = DOM.campoLugar().value.trim();
    const errEl  = DOM.modalError();

    if (!nombre) {
        errEl.textContent = 'El nombre del evento es obligatorio';
        errEl.style.display = 'flex';
        return;
    }

    // Fix JS 10: texto dinámico durante la operación
    setGuardando(true, id ? 'Actualizando...' : 'Creando...');
    errEl.style.display = 'none';

    try {
        const data = { nombre_evento: nombre, fecha: fecha || null, lugar: lugar || null };

        if (id) {
            await CompeticionesAPI.update(id, data);
            showToast('Competicion actualizada correctamente', 'success');
        } else {
            await CompeticionesAPI.create(data);
            showToast('Competicion creada correctamente', 'success');
        }

        cerrarModal();
        cargarCompeticiones();

    } catch (err) {
        // Fix JS 13: distinción error de red vs API
        const msg = err.message.includes('fetch')
            ? 'Error de conexion con el servidor'
            : err.message;
        errEl.textContent = msg;
        errEl.style.display = 'flex';
        setGuardando(false);
    }
}

function setGuardando(loading, texto = 'Guardar') {
    DOM.btnGuardar().disabled = loading;
    DOM.btnGuardarText().style.display    = loading ? 'none'        : 'inline';
    DOM.btnGuardarText().textContent      = texto;
    DOM.btnGuardarSpinner().style.display = loading ? 'inline-block': 'none';
}

// -------------------------------------------------------
// eliminarCompeticion()
// Fix JS 8: retroceder página si queda vacía
// Fix JS 9: toast de éxito
// -------------------------------------------------------
async function eliminarCompeticion(id, nombre) {
    if (!confirm(`Eliminar la competicion "${nombre}"?\n\nEsta accion no se puede deshacer.`)) return;

    try {
        await CompeticionesAPI.delete(id);
        showToast('Competicion eliminada correctamente', 'success');

        // Fix JS 8: si era el último elemento de la página, retroceder
        const filas = DOM.tablaBody().querySelectorAll('tr[data-action]').length;
        if (filas <= 1 && paginaActual > 1) {
            paginaActual--;
        }

        cargarCompeticiones();

    } catch (err) {
        // Fix JS 13
        const msg = err.message.includes('fetch')
            ? 'Error de conexion al eliminar'
            : err.message;
        showToast(msg, 'error');
    }
}

// -------------------------------------------------------
// Fix JS 9: Toast de notificación
// -------------------------------------------------------
function showToast(msg, tipo = 'success') {
    const toast = DOM.toast();
    toast.textContent = msg;
    toast.className   = `toast toast-${tipo} show`;
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
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
