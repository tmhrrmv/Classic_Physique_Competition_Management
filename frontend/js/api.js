/**
 * api.js — Capa de comunicación con el backend REST
 */

const API_BASE = '../backend/api';

function getToken() {
    return sessionStorage.getItem('token') || '';
}

async function apiFetch(endpoint, options = {}) {
    const token = getToken();

    const headers = {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
    };

    const response = await fetch(`${API_BASE}/${endpoint}`, {
        ...options,
        headers,
    });

    if (response.status === 401) {
        sessionStorage.clear();
        window.location.href = 'login.php';
        return;
    }

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || `Error HTTP ${response.status}`);
    }

    return data;
}

// --- Atletas ---
const AtletasAPI = {
    getAll: ()         => apiFetch('atletas.php'),
    getById: (id)      => apiFetch(`atletas.php?id=${id}`),
    create: (body)     => apiFetch('atletas.php',         { method: 'POST',   body: JSON.stringify(body) }),
    update: (id, body) => apiFetch(`atletas.php?id=${id}`, { method: 'PUT',    body: JSON.stringify(body) }),
    remove: (id)       => apiFetch(`atletas.php?id=${id}`, { method: 'DELETE' }),
};

// --- Competiciones ---
const CompeticionesAPI = {
    getAll: ()         => apiFetch('competiciones.php'),
    getById: (id)      => apiFetch(`competiciones.php?id=${id}`),
    create: (body)     => apiFetch('competiciones.php',         { method: 'POST',   body: JSON.stringify(body) }),
    update: (id, body) => apiFetch(`competiciones.php?id=${id}`, { method: 'PUT',    body: JSON.stringify(body) }),
    remove: (id)       => apiFetch(`competiciones.php?id=${id}`, { method: 'DELETE' }),
};

// --- Puntuaciones ---
const PuntuacionesAPI = {
    getByCompeticion: (idComp)   => apiFetch(`puntuaciones.php?id_competicion=${idComp}`),
    getByInscripcion: (idInscr)  => apiFetch(`puntuaciones.php?id_inscripcion=${idInscr}`),
    create: (body)               => apiFetch('puntuaciones.php',       { method: 'POST',   body: JSON.stringify(body) }),
    update: (id, body)           => apiFetch(`puntuaciones.php?id=${id}`, { method: 'PUT',  body: JSON.stringify(body) }),
    remove: (id)                 => apiFetch(`puntuaciones.php?id=${id}`, { method: 'DELETE' }),
};

// --- Resultados ---
const ResultadosAPI = {
    getByCompeticion: (idComp) => apiFetch(`resultados.php?id_competicion=${idComp}`),
    calcular: (idComp)         => apiFetch('resultados.php', {
        method: 'POST',
        body: JSON.stringify({ id_competicion: idComp }),
    }),
};

// --- Auth ---
const AuthAPI = {
    login: (username, password) => apiFetch('auth.php', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    }),
};
