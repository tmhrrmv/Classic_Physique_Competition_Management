<?php
declare(strict_types=1);

// ============================================================
// clases/ConexionDB.php — Patrón Singleton para la conexión BD
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Implementación del patrón Singleton
// v1.1 - Validación constantes, métodos transacciones,
//        log en __clone/__wakeup
// v1.2 - Eliminado PDO::MYSQL_ATTR_INIT_COMMAND
//        No disponible en FrankenPHP sin extensión pdo_mysql
//        El charset utf8mb4 ya está garantizado en el DSN
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

final class ConexionDB
{
    private static ?ConexionDB $instancia = null;
    private PDO $pdo;

    private function __construct()
    {
        // Validar que todas las constantes existen
        $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $const) {
            if (!defined($const)) {
                throw new \RuntimeException("Constante $const no definida en config.php");
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            logError('ConexionDB::__construct', $e);
            http_response_code(500);
            jsonResponse(['error' => 'Error de conexión a la base de datos']);
            exit;
        }
    }

    public static function getInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function getConexion(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollBack(): void         { $this->pdo->rollBack(); }

    private function __clone(): void
    {
        error_log('[SINGLETON] Intento de clonar ConexionDB — IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        throw new \RuntimeException('No se puede clonar un Singleton');
    }

    public function __wakeup(): void
    {
        error_log('[SINGLETON] Intento de deserializar ConexionDB — IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        throw new \RuntimeException('No se puede deserializar un Singleton');
    }
}
