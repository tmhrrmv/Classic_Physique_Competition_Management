// ============================================================
// js/api.js — Funciones para llamar al backend PHP
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Funciones base para todos los endpoints
//        getToken, authHeaders, handleResponse
//        login, getAtletas, getCompeticiones,
//        getPuntuaciones, getResultados
// ============================================================

const API_BASE = '/api';

// -------------------------------------------------------
// Helpers internos
// -------------------------------------------------------

function getToken() {
    return sessionStorage.getItem('token');
}

function authHeaders() {
    return {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`
    };
}

async function handleResponse(res) {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = data.error || data.errors?.join(', ') || `Error ${res.status}`;
        throw new Error(msg);
    }
    return data;
}

// -------------------------------------------------------
// AUTH
// -------------------------------------------------------

const AuthAPI = {
    async login(username, password) {
        const res = await fetch(`${API_BASE}/auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        return handleResponse(res);
    },

    logout() {
        sessionStorage.removeItem('token');
        sessionStorage.removeItem('user');
        window.location.href = '/pages/login.php';
    },

    isLoggedIn() {
        return !!getToken();
    },

    getUser() {
        const u = sessionStorage.getItem('user');
        return u ? JSON.parse(u) : null;
    },

    saveSession(data) {
        sessionStorage.setItem('token', data.token);
        sessionStorage.setItem('user', JSON.stringify({
            username: data.username,
            role:     data.role
        }));
    },

    requireAuth() {
        if (!this.isLoggedIn()) {
            window.location.href = '/pages/login.php';
            return false;
        }
        return true;
    }
};

// -------------------------------------------------------
// COMPETICIONES
// -------------------------------------------------------

const CompeticionesAPI = {
    async getAll(params = {}) {
        const q = new URLSearchParams(params).toString();
        const res = await fetch(`${API_BASE}/competiciones.php${q ? '?' + q : ''}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async getById(id) {
        const res = await fetch(`${API_BASE}/competiciones.php?id=${id}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async create(data) {
        const res = await fetch(`${API_BASE}/competiciones.php`, {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify(data)
        });
        return handleResponse(res);
    },

    async update(id, data) {
        const res = await fetch(`${API_BASE}/competiciones.php?id=${id}`, {
            method: 'PATCH',
            headers: authHeaders(),
            body: JSON.stringify(data)
        });
        return handleResponse(res);
    },

    async delete(id) {
        const res = await fetch(`${API_BASE}/competiciones.php?id=${id}`, {
            method: 'DELETE',
            headers: authHeaders()
        });
        return handleResponse(res);
    }
};

// -------------------------------------------------------
// ATLETAS
// -------------------------------------------------------

const AtletasAPI = {
    async getAll(params = {}) {
        const q = new URLSearchParams(params).toString();
        const res = await fetch(`${API_BASE}/atletas.php${q ? '?' + q : ''}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async getById(id) {
        const res = await fetch(`${API_BASE}/atletas.php?id=${id}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async getHistorial(id, params = {}) {
        const q = new URLSearchParams({ id, historial: 1, ...params }).toString();
        const res = await fetch(`${API_BASE}/atletas.php?${q}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async inscribir(data) {
        const res = await fetch(`${API_BASE}/atletas.php`, {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify(data)
        });
        return handleResponse(res);
    },

    async update(id, data) {
        const res = await fetch(`${API_BASE}/atletas.php?id=${id}`, {
            method: 'PATCH',
            headers: authHeaders(),
            body: JSON.stringify(data)
        });
        return handleResponse(res);
    },

    async desactivar(id) {
        const res = await fetch(`${API_BASE}/atletas.php?id=${id}`, {
            method: 'DELETE',
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async reactivar(id) {
        const res = await fetch(`${API_BASE}/atletas.php?id=${id}&reactivar=1`, {
            method: 'DELETE',
            headers: authHeaders()
        });
        return handleResponse(res);
    }
};

// -------------------------------------------------------
// PUNTUACIONES
// -------------------------------------------------------

const PuntuacionesAPI = {
    async getByCompeticion(id_competicion, params = {}) {
        const q = new URLSearchParams({ id_competicion, ...params }).toString();
        const res = await fetch(`${API_BASE}/puntuaciones.php?${q}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async registrar(data) {
        const res = await fetch(`${API_BASE}/puntuaciones.php`, {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify(data)
        });
        return handleResponse(res);
    },

    async actualizar(id, ranking_otorgado) {
        const res = await fetch(`${API_BASE}/puntuaciones.php?id=${id}`, {
            method: 'PATCH',
            headers: authHeaders(),
            body: JSON.stringify({ ranking_otorgado })
        });
        return handleResponse(res);
    },

    async anular(id) {
        const res = await fetch(`${API_BASE}/puntuaciones.php?id=${id}`, {
            method: 'DELETE',
            headers: authHeaders()
        });
        return handleResponse(res);
    }
};

// -------------------------------------------------------
// RESULTADOS
// -------------------------------------------------------

const ResultadosAPI = {
    async getByCompeticion(id_competicion, params = {}) {
        const q = new URLSearchParams({ id_competicion, ...params }).toString();
        const res = await fetch(`${API_BASE}/resultados.php?${q}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async getHistorialAtleta(id_atleta, params = {}) {
        const q = new URLSearchParams({ id_atleta, ...params }).toString();
        const res = await fetch(`${API_BASE}/resultados.php?${q}`, {
            headers: authHeaders()
        });
        return handleResponse(res);
    },

    async calcular(id_competicion) {
        const res = await fetch(`${API_BASE}/resultados.php`, {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ id_competicion })
        });
        return handleResponse(res);
    },

    async eliminar(id_competicion) {
        const res = await fetch(`${API_BASE}/resultados.php?id_competicion=${id_competicion}`, {
            method: 'DELETE',
            headers: authHeaders()
        });
        return handleResponse(res);
    }
};

// -------------------------------------------------------
// UI helpers compartidos
// -------------------------------------------------------

const UI = {
    showAlert(type, msg, container = document.body) {
        const old = container.querySelector('.alert');
        if (old) old.remove();
        const el = document.createElement('div');
        el.className = `alert alert-${type}`;
        el.textContent = msg;
        container.prepend(el);
        if (type !== 'error') {
            setTimeout(() => el.remove(), 4000);
        }
    },

    showLoading(text = 'Cargando...') {
        const el = document.createElement('div');
        el.className = 'loading-overlay';
        el.id = 'loading-overlay';
        el.innerHTML = `
            <div class="spinner"></div>
            <span class="loading-text">${text}</span>
        `;
        document.body.appendChild(el);
    },

    hideLoading() {
        document.getElementById('loading-overlay')?.remove();
    },

    estadoBadge(estado) {
        const map = {
            abierta:   ['badge-success', 'Abierta'],
            en_curso:  ['badge-warning', 'En Curso'],
            cerrada:   ['badge-default', 'Cerrada'],
            sin_fecha: ['badge-info',    'Sin Fecha'],
        };
        const [cls, label] = map[estado] || ['badge-default', estado];
        return `<span class="badge ${cls}">${label}</span>`;
    },

    initNavActive() {
        const path = window.location.pathname;
        document.querySelectorAll('.nav-item').forEach(item => {
            if (item.getAttribute('href') === path) {
                item.classList.add('active');
            }
        });
    },

    initUserInfo() {
        const user = AuthAPI.getUser();
        if (!user) return;
        const nameEl = document.getElementById('user-name');
        const roleEl = document.getElementById('user-role');
        const avatarEl = document.getElementById('user-avatar');
        if (nameEl) nameEl.textContent = user.username;
        if (roleEl) roleEl.textContent = user.role;
        if (avatarEl) avatarEl.textContent = user.username[0].toUpperCase();
    }
};
