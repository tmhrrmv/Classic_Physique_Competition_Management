USE gestion_competiciones;

-- ============================================================
-- Crear roles
-- ============================================================
CREATE ROLE IF NOT EXISTS 'admin_bd';
CREATE ROLE IF NOT EXISTS 'organizador';
CREATE ROLE IF NOT EXISTS 'juez';
CREATE ROLE IF NOT EXISTS 'consulta_publica';

-- ============================================================
-- Privilegios: admin_bd
-- Acceso total a toda la base de datos
-- ============================================================
GRANT ALL PRIVILEGES ON gestion_competiciones.* TO 'admin_bd';

-- ============================================================
-- Privilegios: organizador
-- Gestiona eventos, atletas e inscripciones
-- No puede modificar puntuaciones
-- ============================================================
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.categoria      TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.competicion    TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.atleta         TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.inscripcion    TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.puntuacion     TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.juez           TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.resultado_final TO 'organizador';

-- ============================================================
-- Privilegios: juez
-- Solo sus propias puntuaciones y consulta básica
-- ============================================================
GRANT SELECT ON gestion_competiciones.categoria      TO 'juez';
GRANT SELECT ON gestion_competiciones.competicion    TO 'juez';
GRANT SELECT (id_inscripcion, id_competicion, id_categoria)
             ON gestion_competiciones.inscripcion    TO 'juez';
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.puntuacion     TO 'juez';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'juez';

-- ============================================================
-- Privilegios: consulta_publica
-- Solo lectura de datos públicos, sin datos sensibles
-- ============================================================
GRANT SELECT (id_atleta, nombre, apellido, nacionalidad)
             ON gestion_competiciones.atleta         TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.competicion    TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.categoria      TO 'consulta_publica';
GRANT SELECT (id_inscripcion, id_competicion, id_categoria, numero_dorsal)
             ON gestion_competiciones.inscripcion    TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'consulta_publica';

-- ============================================================
-- Crear usuarios y asignar roles
-- ============================================================

-- Administrador
CREATE USER IF NOT EXISTS 'admin_competiciones'@'localhost' IDENTIFIED BY 'Admin_2025!';
GRANT 'admin_bd' TO 'admin_competiciones'@'localhost';
SET DEFAULT ROLE 'admin_bd' TO 'admin_competiciones'@'localhost';

-- Organizador
CREATE USER IF NOT EXISTS 'org_madrid'@'localhost' IDENTIFIED BY 'Org_2025!';
GRANT 'organizador' TO 'org_madrid'@'localhost';
SET DEFAULT ROLE 'organizador' TO 'org_madrid'@'localhost';

-- Juez
CREATE USER IF NOT EXISTS 'juez_roberto'@'localhost' IDENTIFIED BY 'Juez_2025!';
GRANT 'juez' TO 'juez_roberto'@'localhost';
SET DEFAULT ROLE 'juez' TO 'juez_roberto'@'localhost';

-- Consulta publica
CREATE USER IF NOT EXISTS 'publico'@'%' IDENTIFIED BY 'Publico_2025!';
GRANT 'consulta_publica' TO 'publico'@'%';
SET DEFAULT ROLE 'consulta_publica' TO 'publico'@'%';

FLUSH PRIVILEGES;
