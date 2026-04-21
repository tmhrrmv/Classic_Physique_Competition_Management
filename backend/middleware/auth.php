<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function requireAuth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!str_starts_with($header, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Token de autorización requerido']);
        exit;
    }

    $token = substr($header, 7);
    $payload = verifyJwt($token);

    if ($payload === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }

    return $payload;
}

function verifyJwt(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    $expectedSig = base64_encode(
        hash_hmac('sha256', "$headerB64.$payloadB64", JWT_SECRET, true)
    );

    if (!hash_equals($expectedSig, $signatureB64)) {
        return null;
    }

    $payload = json_decode(base64_decode($payloadB64), true);

    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
        return null;
    }

    return $payload;
}

function generateJwt(array $data): string
{
    $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode(array_merge($data, ['exp' => time() + JWT_TTL])));
    $sig     = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    return "$header.$payload.$sig";
}
