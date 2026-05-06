<?php
// ============================================================
// pages/logout.php — Cierre de sesión
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - v1.3 - Ver versiones anteriores
// v1.4 - Destruye sesión de MySQL via SessionHandler
//        Sin HTML ni JavaScript necesario
// ============================================================

define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';
startSecureSession();

// Destruir sesión completamente (borra de MySQL también)
clearSessionData();

header('Location: /pages/login.php?reason=logout');
exit;
