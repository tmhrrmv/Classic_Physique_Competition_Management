<?php
declare(strict_types=1);

// ============================================================
// clases/ConexionDB.php — Patrón Singleton para la conexión BD
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Implementación del patrón Singleton
//        Constructor privado con configuración PDO
//        Bloqueo de clonación y deserialización
//        PDO::ATTR_ERRMODE y PDO::ATTR_DEFAULT_FETCH_MODE
// v1.1 - Mejora 3: validación de constantes de BD antes de usarlas
//        Si falta DB_HOST o DB_NAME lanza excepción clara
//        en lugar de un error críptico de PDO
//      - Mejora 6: métodos beginTransaction(), commit(), rollBack()
//        para transacciones explícitas sin exponer PDO
//      - Mejora 8: PDO::MYSQL_ATTR_INIT_COMMAND fuerza utf8mb4
//        aunque el DSN no lo aplique en versiones antiguas
//      - Mejora 10: log en __clone() y __wakeup()
//        Registra si alguien intenta romper el Singleton
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

final class ConexionDB
{
    // -------------------------------------------------------
    // La única instancia de la clase
    // static: pertenece a la clase, no a un objeto concreto
    // -------------------------------------------------------
    private static ?ConexionDB $instancia = null;

    // -------------------------------------------------------
    // El objeto PDO que representa la conexión a MySQL
    // -------------------------------------------------------
    private PDO $pdo;

    // -------------------------------------------------------
    // Constructor PRIVADO
    // Nadie puede hacer: new ConexionDB() desde fuera
    // Solo se ejecuta una vez, la primera vez que se llama
    // a getInstancia()
    // v1.1 mejora 3: valida constantes antes de usarlas
    // -------------------------------------------------------
    private function __construct()
    {
        // Mejora 3: validar que todas las constantes existen
        // Si falta alguna el DSN sería mysql:host=;port=;...
        // lo que generaría un error críptico de PDO
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
                // Lanza excepciones en lugar de errores silenciosos
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Devuelve resultados como arrays asociativos
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Usa prepared statements reales (más seguro)
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Mejora 8: fuerza utf8mb4 aunque el DSN no lo aplique
                // correctamente en versiones antiguas de PHP/MySQL
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]);
        } catch (\PDOException $e) {
            logError('ConexionDB::__construct', $e);
            http_response_code(500);
            jsonResponse(['error' => 'Error de conexión a la base de datos']);
            exit;
        }
    }

    // -------------------------------------------------------
    // getInstancia() — punto de acceso único
    // Si no existe instancia la crea, si ya existe la devuelve
    // Uso: ConexionDB::getInstancia()->getConexion()
    // -------------------------------------------------------
    public static function getInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    // -------------------------------------------------------
    // getConexion() — devuelve el objeto PDO
    // Es el método que usan todos los archivos PHP para
    // ejecutar queries contra la base de datos
    // -------------------------------------------------------
    public function getConexion(): PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------
    // Mejora 6: métodos para transacciones explícitas
    // Los procedures ya usan transacciones internamente,
    // pero tenerlos aquí permite usarlos desde PHP también
    // sin exponer el objeto PDO directamente
    // -------------------------------------------------------
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    // -------------------------------------------------------
    // Bloqueo de clonación
    // v1.1 mejora 10: log antes de lanzar excepción
    // Si alguien intenta clonar queda registro en el log
    // -------------------------------------------------------
    private function __clone(): void
    {
        error_log('[SINGLETON] Intento de clonar ConexionDB bloqueado — IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        throw new \RuntimeException('No se puede clonar un Singleton');
    }

    // -------------------------------------------------------
    // Bloqueo de deserialización
    // v1.1 mejora 10: log antes de lanzar excepción
    // Si alguien intenta deserializar queda registro en el log
    // -------------------------------------------------------
    public function __wakeup(): void
    {
        error_log('[SINGLETON] Intento de deserializar ConexionDB bloqueado — IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        throw new \RuntimeException('No se puede deserializar un Singleton');
    }
}
