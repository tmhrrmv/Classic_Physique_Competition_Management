-- ============================================================
-- 05_users_roles.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Roles y usuarios iniciales:
--        admin_bd, organizador, juez, consulta_publica
-- ============================================================

USE gestion_competiciones;

-- Crear roles
CREATE ROLE IF NOT EXISTS 'admin_bd';
CREATE ROLE IF NOT EXISTS 'organizador';
CREATE ROLE IF NOT EXISTS 'juez';
CREATE ROLE IF NOT EXISTS 'consulta_publica';

-- -------------------------------------------------------
-- admin_bd: acceso total
-- -------------------------------------------------------
GRANT ALL PRIVILEGES ON gestion_competiciones.* TO 'admin_bd';

-- -------------------------------------------------------
-- organizador: gestiona eventos, atletas e inscripciones
-- No puede modificar puntuaciones ni resultados
-- -------------------------------------------------------
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.categoria       TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.competicion     TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.atleta          TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.inscripcion     TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.puntuacion      TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.juez            TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.resultado_final TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.log_procedimientos TO 'organizador';

-- -------------------------------------------------------
-- juez: solo sus propias puntuaciones y consulta básica
-- Solo jueces activos pueden puntuar (fn_juez_existe)
-- -------------------------------------------------------
GRANT SELECT ON gestion_competiciones.categoria      TO 'juez';
GRANT SELECT ON gestion_competiciones.competicion    TO 'juez';
GRANT SELECT (id_inscripcion, id_competicion, id_categoria)
             ON gestion_competiciones.inscripcion    TO 'juez';
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.puntuacion      TO 'juez';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'juez';

-- -------------------------------------------------------
-- consulta_publica: solo lectura de datos públicos
-- Sin acceso a datos sensibles (peso, fecha_nacimiento)
-- -------------------------------------------------------
GRANT SELECT (id_atleta, nombre, apellido, nacionalidad)
             ON gestion_competiciones.atleta          TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.competicion     TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.categoria       TO 'consulta_publica';
GRANT SELECT (id_inscripcion, id_competicion, id_categoria, numero_dorsal)
             ON gestion_competiciones.inscripcion     TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'consulta_publica';

-- -------------------------------------------------------
-- Crear usuarios y asignar roles
-- -------------------------------------------------------
CREATE USER IF NOT EXISTS 'admin_competiciones'@'localhost' IDENTIFIED BY 'Admin_2025!';
GRANT 'admin_bd' TO 'admin_competiciones'@'localhost';
SET DEFAULT ROLE 'admin_bd' TO 'admin_competiciones'@'localhost';

CREATE USER IF NOT EXISTS 'org_madrid'@'localhost' IDENTIFIED BY 'Org_2025!';
GRANT 'organizador' TO 'org_madrid'@'localhost';
SET DEFAULT ROLE 'organizador' TO 'org_madrid'@'localhost';

CREATE USER IF NOT EXISTS 'juez_roberto'@'localhost' IDENTIFIED BY 'Juez_2025!';
GRANT 'juez' TO 'juez_roberto'@'localhost';
SET DEFAULT ROLE 'juez' TO 'juez_roberto'@'localhost';

CREATE USER IF NOT EXISTS 'publico'@'%' IDENTIFIED BY 'Publico_2025!';
GRANT 'consulta_publica' TO 'publico'@'%';
SET DEFAULT ROLE 'consulta_publica' TO 'publico'@'%';

FLUSH PRIVILEGES;
