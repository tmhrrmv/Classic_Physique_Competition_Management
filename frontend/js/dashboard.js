/**
 * dashboard.js — Carga de estadísticas y listado del dashboard
 */

document.addEventListener('DOMContentLoaded', async () => {
    await loadStats();
    await loadProximasCompeticiones();
});

async function loadStats() {
    try {
        const [competiciones, atletas] = await Promise.all([
            CompeticionesAPI.getAll(),
            AtletasAPI.getAll(),
        ]);

        document.getElementById('stat-competiciones').textContent = competiciones.length;
        document.getElementById('stat-atletas').textContent       = atletas.length;
    } catch (err) {
        console.error('Error cargando estadísticas:', err);
    }
}

async function loadProximasCompeticiones() {
    const wrapper = document.getElementById('lista-competiciones');

    try {
        const competiciones = await CompeticionesAPI.getAll();
        const hoy  = new Date().toISOString().split('T')[0];
        const proximas = competiciones
            .filter(c => c.fecha >= hoy)
            .slice(0, 5);

        if (!proximas.length) {
            wrapper.innerHTML = '<p class="text-muted">No hay competiciones próximas.</p>';
            return;
        }

        const filas = proximas.map(c => `
            <tr>
                <td>${escapeHtml(c.nombre_evento)}</td>
                <td>${c.fecha ?? '–'}</td>
                <td>${escapeHtml(c.lugar ?? '–')}</td>
                <td>
                    <a href="competiciones.php" class="btn btn-outline">Ver</a>
                </td>
            </tr>
        `).join('');

        wrapper.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr><th>Evento</th><th>Fecha</th><th>Lugar</th><th></th></tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        `;
    } catch (err) {
        wrapper.innerHTML = `<p class="text-danger">Error: ${escapeHtml(err.message)}</p>`;
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
