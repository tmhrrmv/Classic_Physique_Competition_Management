<?php
declare(strict_types=1);

// ============================================================
// config/db.php — Conexión a la base de datos
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Conexión PDO básica
// v1.1 - Singleton pattern
// v1.2 - Charset utf8mb4, opciones PDO explícitas
// v1.3 - Error controlado con jsonResponse si falla
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (\PDOException $e) {
        logError('db.php getConnection', $e);
        http_response_code(500);
        jsonResponse(['error' => 'Error de conexión a la base de datos']);
        exit;
    }

    return $pdo;
}
