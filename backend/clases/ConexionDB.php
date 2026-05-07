<?php
declare(strict_types=1);

// ============================================================
// clases/ConexionDB.php — Patrón Singleton para la conexión BD
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Implementación del patrón Singleton con PDO
// v1.1 - Validación constantes, métodos transacciones,
//        log en __clone/__wakeup
// v1.2 - Eliminado PDO::MYSQL_ATTR_INIT_COMMAND
// v1.3 - Cambiado de PDO a mysqli
//        FrankenPHP en Railway no tiene pdo_mysql compilado
//        mysqli funciona con mysqlnd que sí está disponible
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

final class ConexionDB
{
    private static ?ConexionDB $instancia = null;
    private \mysqli $conexion;

    private function __construct()
    {
        // Validar que todas las constantes existen
        $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $const) {
            if (!defined($const)) {
                throw new \RuntimeException("Constante $const no definida en config.php");
            }
        }

        $this->conexion = new \mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            (int) DB_PORT
        );

        if ($this->conexion->connect_error) {
            $error = $this->conexion->connect_error;
            logError('ConexionDB::__construct', new \RuntimeException($error));
            http_response_code(500);
            jsonResponse(['error' => 'Error de conexión a la base de datos']);
            exit;
        }

        // Forzar charset utf8mb4
        $this->conexion->set_charset('utf8mb4');

    }

    public static function getInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    // -------------------------------------------------------
    // getConexion() — devuelve el objeto mysqli
    // -------------------------------------------------------
    public function getConexion(): \mysqli
    {
        return $this->conexion;
    }

    // -------------------------------------------------------
    // getPDO() — crea un PDO wrapper sobre mysqli
    // Para compatibilidad con código existente que usa PDO
    // Usa DSN de mysqli ya conectado
    // -------------------------------------------------------
    public function getPDO(): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        try {
            $pdo = new \PDO($dsn, DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $pdo;
        } catch (\PDOException $e) {
            logError('ConexionDB::getPDO', $e);
            http_response_code(500);
            jsonResponse(['error' => 'Error de conexión a la base de datos']);
            exit;
        }
    }

    public function beginTransaction(): void { $this->conexion->begin_transaction(); }
    public function commit(): void           { $this->conexion->commit(); }
    public function rollBack(): void         { $this->conexion->rollback(); }

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
