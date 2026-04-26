<?php
declare(strict_types=1);

// ============================================================
// middleware/roles.php — Control de acceso por rol
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Roles básicos: admin, juez, guest
// v1.1 - Añadido ROLE_ORGANIZADOR y ROLE_CONSULTA
// v1.2 - Eliminado 'required' de respuesta 403
//        defined() antes de cada constante
// v1.3 - Usa jsonResponse() de helpers.php
// ============================================================

require_once __DIR__ . '/../helpers.php';

if (!defined('ROLE_ADMIN'))       define('ROLE_ADMIN',       'admin');
if (!defined('ROLE_ORGANIZADOR')) define('ROLE_ORGANIZADOR', 'organizador');
if (!defined('ROLE_JUEZ'))        define('ROLE_JUEZ',        'juez');
if (!defined('ROLE_CONSULTA'))    define('ROLE_CONSULTA',    'consulta_publica');
if (!defined('ROLE_GUEST'))       define('ROLE_GUEST',       'guest');

function requireRole(array $payload, string ...$allowedRoles): void
{
    $userRole = $payload['role'] ?? ROLE_GUEST;

    if (!in_array($userRole, $allowedRoles, true)) {
        jsonResponse(['error' => 'Acceso denegado'], 403);
        exit;
    }
}

function isAdmin(array $payload): bool
{
    return ($payload['role'] ?? '') === ROLE_ADMIN;
}

function isOrganizador(array $payload): bool
{
    return ($payload['role'] ?? '') === ROLE_ORGANIZADOR;
}

function isJuez(array $payload): bool
{
    return ($payload['role'] ?? '') === ROLE_JUEZ;
}
