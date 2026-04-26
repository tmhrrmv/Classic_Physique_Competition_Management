<?php
declare(strict_types=1);

// ============================================================
// middleware/auth.php — Verificación y generación de JWT
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - JWT básico con Base64 estándar
// v1.1 - Añadido requireAuth y verifyJwt
// v1.2 - Base64 URL-safe en generateJwt y verifyJwt
// v1.3 - Validación espacios extra en header Authorization
//      - iss y aud en JWT desde config.php
//      - isTokenExpired() separada
//      - verifyJwt devuelve razón específica del error
// v1.4 - Comprobación de longitud antes de hash_equals
//      - Validación de que payload sea objeto JSON
//      - base64url_decode valida resultado
// v1.5 - Mejora 6: generateJwt verifica que json_encode
//        no falle antes de construir el JWT
//      - Mejora 7: detección de array numérico mejorada
//        usando empty() para cubrir payload vacío {}
//      - Mejora 8: límite de longitud del token en
//        requireAuth (máx 2048 chars) para evitar tokens
//        de tamaño arbitrario
// ============================================================

require_once __DIR__ . '/../config.php';

// Longitud máxima permitida para un JWT
// v1.5 mejora 8: evita procesar tokens de tamaño arbitrario
define('JWT_MAX_LENGTH', 2048);

// -------------------------------------------------------
// v1.2: helpers Base64 URL-safe
// -------------------------------------------------------
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// v1.4: base64url_decode valida el resultado
function base64url_decode(string $data): ?string
{
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

// -------------------------------------------------------
// v1.3: isTokenExpired() separada
// -------------------------------------------------------
function isTokenExpired(array $payload): bool
{
    return ($payload['exp'] ?? 0) < time();
}

// -------------------------------------------------------
// requireAuth
// v1.3: limpia espacios extra con preg_match
// v1.5 mejora 8: límite de longitud del token
// -------------------------------------------------------
function requireAuth(): array
{
    $header = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/^Bearer\s+(\S+)$/', $header, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token de autorización requerido']);
        exit;
    }

    $token = $matches[1];

    // v1.5 mejora 8: rechazar tokens demasiado largos
    if (strlen($token) > JWT_MAX_LENGTH) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }

    $result = verifyJwt($token);

    if (isset($result['error'])) {
        http_response_code(401);
        echo json_encode(['error' => $result['error']]);
        exit;
    }

    return $result['payload'];
}

// -------------------------------------------------------
// verifyJwt
// v1.4: longitud antes de hash_equals, validación payload
// v1.5 mejora 7: detección de array numérico mejorada
// -------------------------------------------------------
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

    // v1.4: verificar longitud antes de hash_equals
    if (strlen($expectedSig) !== strlen($signatureB64)) {
        return ['error' => 'Firma del token inválida'];
    }

    if (!hash_equals($expectedSig, $signatureB64)) {
        return ['error' => 'Firma del token inválida'];
    }

    // v1.4: base64url_decode con validación
    $payloadJson = base64url_decode($payloadB64);
    if ($payloadJson === null) {
        return ['error' => 'Payload del token no es Base64 válido'];
    }

    $payload = json_decode($payloadJson, true);

    // v1.5 mejora 7: detección mejorada de payload inválido
    // Cubre array numérico [] y payload vacío {}
    if (!is_array($payload) || empty($payload)) {
        return ['error' => 'Payload del token inválido'];
    }

    // Verificar que no sea array numérico (JSON [])
    if (isset($payload[0])) {
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

// -------------------------------------------------------
// generateJwt
// v1.2: base64url_encode
// v1.3: iss y aud al payload
// v1.5 mejora 6: verifica que json_encode no falle
// -------------------------------------------------------
function generateJwt(array $data): string
{
    $headerJson = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payloadData = array_merge($data, [
        'exp' => time() + JWT_TTL,
        'iss' => JWT_ISS,
        'aud' => JWT_AUD,
    ]);
    $payloadJson = json_encode($payloadData);

    // v1.5 mejora 6: json_encode puede devolver false
    // si hay valores no serializables en $data
    if ($headerJson === false || $payloadJson === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno al generar el token']);
        exit;
    }

    $header  = base64url_encode($headerJson);
    $payload = base64url_encode($payloadJson);
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    return "$header.$payload.$sig";
}
