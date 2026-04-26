<?php
declare(strict_types=1);

// ============================================================
// helpers.php — Funciones auxiliares compartidas
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - getClientIp, sanitize, cleanSpError,
//        validateContentType, validateJsonBody,
//        validateIntPositive, jsonResponse, logError,
//        addLocationHeader, methodNotAllowed,
//        getPaginationParams, paginatedResponse
// ============================================================

if (defined('HELPERS_LOADED')) return;
define('HELPERS_LOADED', true);

// -------------------------------------------------------
// getClientIp
// IP real validando TRUSTED_PROXIES
// -------------------------------------------------------
function getClientIp(): string
{
    $remoteIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if ($forwarded !== '' && in_array($remoteIp, TRUSTED_PROXIES, true)) {
        $ip = trim(explode(',', $forwarded)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $remoteIp;
}

// -------------------------------------------------------
// sanitize
// -------------------------------------------------------
function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------
// cleanSpError
// Limpia el mensaje de error de un sp_ para el cliente
// -------------------------------------------------------
function cleanSpError(string $message): string
{
    if (preg_match('/:\s*(.+)$/', $message, $matches)) {
        $clean = trim($matches[1]);
        return $clean !== '' ? $clean : 'Error al procesar la operación';
    }
    return 'Error al procesar la operación';
}

// -------------------------------------------------------
// validateContentType
// -------------------------------------------------------
function validateContentType(): void
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') === false) {
        http_response_code(415);
        jsonResponse(['error' => 'Content-Type debe ser application/json']);
        exit;
    }
}

// -------------------------------------------------------
// validateJsonBody
// -------------------------------------------------------
function validateJsonBody(string $raw): ?array
{
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// -------------------------------------------------------
// validateIntPositive
// -------------------------------------------------------
function validateIntPositive(mixed $value): ?int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false || $int === null || $int <= 0) {
        return null;
    }
    return $int;
}

// -------------------------------------------------------
// jsonResponse
// json_encode seguro — si falla devuelve error 500
// -------------------------------------------------------
function jsonResponse(mixed $data, int $code = 200): void
{
    http_response_code($code);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo '{"error":"Error interno al serializar la respuesta"}';
        return;
    }
    echo $json;
}

// -------------------------------------------------------
// logError
// Solo loguea errores 5xx con stack trace y REQUEST_ID
// -------------------------------------------------------
function logError(string $context, \Throwable $e, bool $is5xx = true): void
{
    if (!$is5xx) return;
    error_log(sprintf(
        '[%s] %s | %s | %s',
        defined('REQUEST_ID') ? REQUEST_ID : 'NO-ID',
        $context,
        $e->getMessage(),
        $e->getTraceAsString()
    ));
}

// -------------------------------------------------------
// addLocationHeader
// Header Location estándar REST en respuestas 201
// -------------------------------------------------------
function addLocationHeader(string $resource, int $id): void
{
    header('Location: ' . APP_URL . '/api/' . $resource . '/' . $id);
}

// -------------------------------------------------------
// methodNotAllowed
// Header Allow estándar en respuestas 405
// -------------------------------------------------------
function methodNotAllowed(array $allowed): void
{
    header('Allow: ' . implode(', ', $allowed));
    jsonResponse(['error' => 'Método no permitido'], 405);
}

// -------------------------------------------------------
// getPaginationParams
// -------------------------------------------------------
function getPaginationParams(int $defaultLimit = 20, int $maxLimit = 100): array
{
    $page  = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?: 1));
    $limit = min($maxLimit, max(1, (int)(filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: $defaultLimit)));
    return [
        'page'   => $page,
        'limit'  => $limit,
        'offset' => ($page - 1) * $limit,
    ];
}

// -------------------------------------------------------
// paginatedResponse
// Respuesta paginada con X-Total-Count en header
// Usa FOUND_ROWS() — requiere SQL_CALC_FOUND_ROWS en query
// -------------------------------------------------------
function paginatedResponse(PDO $pdo, array $data, array $pagination): void
{
    $total = (int) $pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
    header('X-Total-Count: ' . $total);
    header('Cache-Control: no-store, max-age=0');
    jsonResponse([
        'data'       => $data,
        'pagination' => [
            'page'        => $pagination['page'],
            'limit'       => $pagination['limit'],
            'total'       => $total,
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ],
    ]);
}
