<?php
// ============================================================
// frontend/index.php — Punto de entrada del frontend
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Redirige según sesión activa
// v1.1 - Usa auth_check.php con SessionHandler MySQL
// v1.2 - Simplificado — solo redirige al login o dashboard
//        El router raíz (index.php) maneja el routing
// ============================================================

define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/includes/auth_check.php';
startSecureSession();

if (isset($_SESSION['usuario'])) {
    header('Location: /pages/dashboard.php');
} else {
    header('Location: /pages/login.php');
}
exit;