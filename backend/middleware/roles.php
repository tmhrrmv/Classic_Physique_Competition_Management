<?php
declare(strict_types=1);

// Roles disponibles en el sistema
const ROLE_ADMIN = 'admin';
const ROLE_JUEZ  = 'juez';
const ROLE_GUEST = 'guest';

function requireRole(array $payload, string ...$allowedRoles): void
{
    $userRole = $payload['role'] ?? ROLE_GUEST;

    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Acceso denegado',
            'required' => $allowedRoles,
            'current'  => $userRole,
        ]);
        exit;
    }
}

function isAdmin(array $payload): bool
{
    return ($payload['role'] ?? '') === ROLE_ADMIN;
}

function isJuez(array $payload): bool
{
    return ($payload['role'] ?? '') === ROLE_JUEZ;
}
