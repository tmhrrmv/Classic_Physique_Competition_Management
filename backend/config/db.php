<?php
declare(strict_types=1);

// ============================================================
// config/db.php — Punto de acceso a la BD via Singleton
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Conexión PDO básica
// v1.1 - Singleton con función getConnection()
// v1.2 - Charset utf8mb4, opciones PDO explícitas
// v1.3 - Migrado a clase ConexionDB (patrón Singleton)
//        Toda llamada pasa por ConexionDB::getInstancia()
// ============================================================

require_once __DIR__ . '/../clases/ConexionDB.php';

// -------------------------------------------------------
// Función de compatibilidad
// Permite usar getConnection() en todos los archivos PHP
// sin cambiar cada llamada individualmente
// Internamente usa ConexionDB::getInstancia()->getConexion()
// -------------------------------------------------------
function getConnection(): PDO
{
    return ConexionDB::getInstancia()->getConexion();
}
