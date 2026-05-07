<?php
// ============================================================
// config/db.php — Obtiene la conexión PDO via ConexionDB
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Conexión directa PDO
// v1.1 - Usa ConexionDB Singleton
// v1.2 - Usa getPDO() para compatibilidad con mysqli
// ============================================================

require_once __DIR__ . '/../clases/ConexionDB.php';

function getConnection(): \PDO
{
    return ConexionDB::getInstancia()->getPDO();
}
