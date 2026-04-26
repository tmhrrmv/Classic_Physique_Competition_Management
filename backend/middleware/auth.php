<?php
declare(strict_types=1);

// ============================================================
// middleware/auth.php — Verificación y generación de JWT
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - JWT básico Base64 estándar
// v1.1 - requireAuth y verifyJwt
// v1.2 - Base64 URL-safe
// v1.3 - Validación espacios header, iss/aud, isTokenExpired
//        verifyJwt devuelve razón específica
// v1.4 - Longitud antes de hash_equals
//        Validación payload objeto JSON
//        base64url_decode valida resultado
// v1.5 - generateJwt verifica json_encode
//        Detección array numérico mejorada
//        JWT_MAX_LENGTH en requireAuth
// v1.6 - Usa jsonResponse() de helpers.php
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if (!defined('JWT_MAX_LENGTH')) define('JWT_MAX_LENGTH', 2048);

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): ?string
{
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function isTokenExpired(array $payload): bool
{
    return ($payload['exp'] ?? 0) < time();
}

function requireAuth(): array
{
    $header = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/^Bearer\s+(\S+)$/', $header, $matches)) {
        jsonResponse(['error' => 'Token de autorización requerido'], 401);
        exit;
    }

    $token = $matches[1];

    if (strlen($token) > JWT_MAX_LENGTH) {
        jsonResponse(['error' => 'Token inválido'], 401);
        exit;
    }

    $result = verifyJwt($token);

    if (isset($result['error'])) {
        jsonResponse(['error' => $result['error']], 401);
        exit;
    }

    return $result['payload'];
}

function verifyJwt(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return ['error' => 'Token mal formado'];
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true)
    );

    if (strlen($expectedSig) !== strlen($signatureB64)) {
        return ['error' => 'Firma del token inválida'];
    }

    if (!hash_equals($expectedSig, $signatureB64)) {
        return ['error' => 'Firma del token inválida'];
    }

    $payloadJson = base64url_decode($payloadB64);
    if ($payloadJson === null) {
        return ['error' => 'Payload del token no es Base64 válido'];
    }

    $payload = json_decode($payloadJson, true);

    if (!is_array($payload) || empty($payload) || isset($payload[0])) {
        return ['error' => 'Payload del token inválido'];
    }

    if (isTokenExpired($payload)) {
        return ['error' => 'Token expirado'];
    }

    if (($payload['iss'] ?? '') !== JWT_ISS) {
        return ['error' => 'Token emitido por sistema no reconocido'];
    }

    if (($payload['aud'] ?? '') !== JWT_AUD) {
        return ['error' => 'Token no válido para este sistema'];
    }

    return ['payload' => $payload];
}

function generateJwt(array $data): string
{
    $headerJson  = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payloadJson = json_encode(array_merge($data, [
        'exp' => time() + JWT_TTL,
        'iss' => JWT_ISS,
        'aud' => JWT_AUD,
    ]));

    if ($headerJson === false || $payloadJson === false) {
        logError('generateJwt', new \RuntimeException('json_encode falló'));
        jsonResponse(['error' => 'Error interno al generar el token'], 500);
        exit;
    }

    $header  = base64url_encode($headerJson);
    $payload = base64url_encode($payloadJson);
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    return "$header.$payload.$sig";
}
