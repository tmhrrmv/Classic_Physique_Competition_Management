<?php
declare(strict_types=1);

// ============================================================
// middleware/auth.php — Verificación y generación de JWT
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - JWT básico con Base64 estándar
// v1.1 - Añadido requireAuth y verifyJwt
// v1.2 - Base64 URL-safe en generateJwt y verifyJwt
// v1.3 - Mejora 5: validación de espacios extra en header
//      - Mejora 6: iss y aud en JWT desde config.php
//      - Mejora 7: función isTokenExpired() separada
//      - Mejora 8: verifyJwt devuelve razón específica
//        en lugar de null silencioso para facilitar debugging
// ============================================================

require_once __DIR__ . '/../config.php';

// -------------------------------------------------------
// v1.2: helpers Base64 URL-safe
// PHP no tiene base64url nativo, se implementa manualmente
// Sustituye +→- /→_ y elimina =
// -------------------------------------------------------
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

// -------------------------------------------------------
// v1.3 mejora 7: isTokenExpired() separada
// Comprueba si el token ha expirado
// -------------------------------------------------------
function isTokenExpired(array $payload): bool
{
    return ($payload['exp'] ?? 0) < time();
}

// -------------------------------------------------------
// requireAuth
// Extrae y verifica el JWT del header Authorization.
// v1.3 mejora 5: limpia espacios extra del header
// -------------------------------------------------------
function requireAuth(): array
{
    $header = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    // v1.3 mejora 5: trim() y verificar Bearer con un solo espacio
    if (!preg_match('/^Bearer\s+(\S+)$/', $header, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token de autorización requerido']);
        exit;
    }

    $token  = $matches[1];
    $result = verifyJwt($token);

    // v1.3 mejora 8: verifyJwt devuelve array con payload o error
    if (isset($result['error'])) {
        http_response_code(401);
        echo json_encode(['error' => $result['error']]);
        exit;
    }

    return $result['payload'];
}

// -------------------------------------------------------
// verifyJwt
// v1.2: usa base64url_decode
// v1.3 mejora 8: devuelve ['payload' => ...] si válido
//               o ['error' => '...'] con razón específica
// -------------------------------------------------------
function verifyJwt(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return ['error' => 'Token mal formado'];
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    // Verificar firma
    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true)
    );

    if (!hash_equals($expectedSig, $signatureB64)) {
        return ['error' => 'Firma del token inválida'];
    }

    // Decodificar payload
    $payload = json_decode(base64url_decode($payloadB64), true);

    if (!is_array($payload)) {
        return ['error' => 'Payload del token inválido'];
    }

    // v1.3 mejora 7: usar isTokenExpired()
    if (isTokenExpired($payload)) {
        return ['error' => 'Token expirado'];
    }

    // v1.3 mejora 6: verificar iss y aud
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
// v1.2: usa base64url_encode
// v1.3 mejora 6: añade iss y aud al payload
// -------------------------------------------------------
function generateJwt(array $data): string
{
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    // v1.3 mejora 6: iss y aud desde config.php
    $payload = base64url_encode(json_encode(array_merge($data, [
        'exp' => time() + JWT_TTL,
        'iss' => JWT_ISS,
        'aud' => JWT_AUD,
    ])));

    $sig = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    return "$header.$payload.$sig";
}
