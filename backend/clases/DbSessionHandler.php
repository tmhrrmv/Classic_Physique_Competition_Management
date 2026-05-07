<?php
declare(strict_types=1);

// ============================================================
// clases/DbSessionHandler.php — Sesiones PHP en MySQL
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Implementación con PDO
// v1.1 - Cambiado a mysqli para compatibilidad con Railway
//        FrankenPHP no tiene pdo_mysql, sí tiene mysqlnd
// ============================================================

class DbSessionHandler implements SessionHandlerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool
    {
        // Crear tabla si no existe
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS sesiones (
                id          VARCHAR(128) NOT NULL,
                data        MEDIUMTEXT   NOT NULL,
                last_access DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_sesiones_last_access (last_access)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->db->prepare(
            'SELECT data FROM sesiones WHERE id = ? AND last_access > DATE_SUB(NOW(), INTERVAL 2 HOUR)'
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sesiones (id, data, last_access)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = NOW()'
        );
        $stmt->bind_param('ss', $id, $data);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM sesiones WHERE id = ?');
        $stmt->bind_param('s', $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function gc(int $maxlifetime): int|false
    {
        $stmt = $this->db->prepare(
            'DELETE FROM sesiones WHERE last_access < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->bind_param('i', $maxlifetime);
        $stmt->execute();
        $rows = $stmt->affected_rows;
        $stmt->close();
        return $rows;
    }
}
