/**
 * auth.js — Gestión de sesión y login/logout
 */

document.addEventListener('DOMContentLoaded', () => {
    const formLogin   = document.getElementById('form-login');
    const btnLogout   = document.getElementById('btn-logout');
    const loginError  = document.getElementById('login-error');

    // Protege páginas que no son login
    const publicPages = ['login.php'];
    const currentPage = window.location.pathname.split('/').pop();

    if (!publicPages.includes(currentPage) && !sessionStorage.getItem('token')) {
        window.location.href = 'login.php';
        return;
    }

    // Formulario de login
    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            loginError.hidden = true;

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            try {
                const res = await AuthAPI.login(username, password);
                sessionStorage.setItem('token', res.token);
                sessionStorage.setItem('role',  res.role);
                window.location.href = 'dashboard.php';
            } catch (err) {
                loginError.textContent = err.message;
                loginError.hidden = false;
            }
        });
    }

    // Botón cerrar sesión
    if (btnLogout) {
        btnLogout.addEventListener('click', () => {
            sessionStorage.clear();
            window.location.href = 'login.php';
        });
    }
});

function getRole() {
    return sessionStorage.getItem('role') || 'guest';
}

function isAdmin() {
    return getRole() === 'admin';
}
