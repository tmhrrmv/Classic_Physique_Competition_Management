<?php
declare(strict_types=1);

// ============================================================
// clases/DbSessionHandler.php — Manejador de sesiones en MySQL
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Implementación SessionHandlerInterface
//        Lee/escribe sesiones en tabla sesiones de MySQL
//        Soluciona problema de múltiples workers en Railway
//        gc() limpia sesiones expiradas automáticamente
// ============================================================

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Abre la sesión — no necesitamos hacer nada aquí
    public function open(string $path, string $name): bool
    {
        return true;
    }

    // Cierra la sesión — no necesitamos hacer nada aquí
    public function close(): bool
    {
        return true;
    }

    // Lee los datos de la sesión desde MySQL
    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT data FROM sesiones WHERE id = ? AND last_access > DATE_SUB(NOW(), INTERVAL 2 HOUR)'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    // Escribe los datos de la sesión en MySQL
    // UPSERT — crea si no existe, actualiza si existe
    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sesiones (id, data, last_access)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = NOW()'
        );
        return $stmt->execute([$id, $data]);
    }

    // Destruye la sesión — borra de MySQL
    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sesiones WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // Limpia sesiones expiradas — se ejecuta periódicamente
    // $maxlifetime viene de session.gc_maxlifetime (por defecto 1440 seg)
    // Nosotros usamos 2 horas (7200 seg)
    public function gc(int $maxlifetime): int|false
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sesiones WHERE last_access < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$maxlifetime]);
        return $stmt->rowCount();
    }
}
