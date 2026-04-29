<?php
declare(strict_types=1);

// ============================================================
// pages/logout.php — Cierre de sesión seguro
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Destruye sesión y redirige al login
// v1.1 - Borra cookie de sesión del navegador
// v1.2 - Usa destroySession() de auth_check
// v1.3 - Fix 2: eliminado HTML antes de destroySession()
//        headers already sent causaba fallo en la redirección
//        El JS que borraba sessionStorage se mueve al login.php
//        que detecta ?reason=logout y limpia el storage
// ============================================================

// Fix 3: no forzar autenticación al incluir auth_check
define('AUTH_CHECK_SKIP_AUTO', true);
require_once __DIR__ . '/../includes/auth_check.php';

// Fix 2: ningún output antes de destroySession()
// destroySession() envía header Location y termina el script
destroySession('logout');