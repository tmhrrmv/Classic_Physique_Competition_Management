-- ============================================================
-- 03_functions.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Funciones auxiliares básicas:
--        fn_get_atleta_id, fn_inscripcion_existe,
--        fn_juez_existe, fn_competicion_existe,
--        fn_ya_inscrito, fn_contar_puntuaciones_evento
-- v1.1 - fn_juez_existe: ahora comprueba también activo=1
--        Un juez inactivo no puede puntuar
-- v1.2 - Nueva: fn_estado_competicion
--        Calcula el estado en tiempo real según la fecha
--        Sustituye el campo estado eliminado de competicion
--      - Nueva: fn_edad_atleta
--        Calcula la edad del atleta en la fecha del evento
--      - Nueva: fn_categoria_valida_para_edad
--        Comprueba si la edad es válida para la categoría
-- ============================================================

USE gestion_competiciones;

DROP FUNCTION IF EXISTS fn_get_atleta_id;
DROP FUNCTION IF EXISTS fn_inscripcion_existe;
DROP FUNCTION IF EXISTS fn_juez_existe;
DROP FUNCTION IF EXISTS fn_competicion_existe;
DROP FUNCTION IF EXISTS fn_ya_inscrito;
DROP FUNCTION IF EXISTS fn_contar_puntuaciones_evento;
DROP FUNCTION IF EXISTS fn_estado_competicion;
DROP FUNCTION IF EXISTS fn_edad_atleta;
DROP FUNCTION IF EXISTS fn_categoria_valida_para_edad;

DELIMITER $$

-- ============================================================
-- fn_get_atleta_id
-- Devuelve id_atleta si existe, NULL si no.
-- Fusiona existencia + recuperar ID en una sola consulta,
-- evitando dos SELECT separados en sp_inscribir_atleta.
-- ============================================================
CREATE FUNCTION fn_get_atleta_id(
  p_nombre           VARCHAR(100),
  p_apellido         VARCHAR(100),
  p_fecha_nacimiento DATE
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_id INT DEFAULT NULL;
  SELECT id_atleta INTO v_id
    FROM atleta
   WHERE nombre           = p_nombre
     AND apellido         = p_apellido
     AND fecha_nacimiento = p_fecha_nacimiento
   LIMIT 1;
  RETURN v_id;
END$$


-- ============================================================
-- fn_inscripcion_existe
-- Devuelve 1 si la inscripción existe, 0 si no.
-- ============================================================
CREATE FUNCTION fn_inscripcion_existe(
  p_id_inscripcion INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_existe TINYINT(1) DEFAULT 0;
  SELECT COUNT(*) INTO v_existe
    FROM inscripcion WHERE id_inscripcion = p_id_inscripcion;
  RETURN v_existe;
END$$


-- ============================================================
-- fn_juez_existe
-- v1.1: comprueba que el juez existe Y está activo.
-- Un juez inactivo (retirado) no puede registrar puntuaciones
-- aunque sus puntuaciones anteriores siguen en el sistema.
-- ============================================================
CREATE FUNCTION fn_juez_existe(
  p_id_juez INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_existe TINYINT(1) DEFAULT 0;
  SELECT COUNT(*) INTO v_existe
    FROM juez WHERE id_juez = p_id_juez AND activo = 1;
  RETURN v_existe;
END$$


-- ============================================================
-- fn_competicion_existe
-- Devuelve 1 si la competición existe, 0 si no.
-- ============================================================
CREATE FUNCTION fn_competicion_existe(
  p_id_competicion INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_existe TINYINT(1) DEFAULT 0;
  SELECT COUNT(*) INTO v_existe
    FROM competicion WHERE id_competicion = p_id_competicion;
  RETURN v_existe;
END$$


-- ============================================================
-- fn_ya_inscrito
-- Devuelve 1 si el atleta ya está inscrito en ese evento.
-- ============================================================
CREATE FUNCTION fn_ya_inscrito(
  p_id_atleta      INT,
  p_id_competicion INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_count TINYINT(1) DEFAULT 0;
  SELECT COUNT(*) INTO v_count
    FROM inscripcion
   WHERE id_atleta      = p_id_atleta
     AND id_competicion = p_id_competicion;
  RETURN v_count > 0;
END$$


-- ============================================================
-- fn_contar_puntuaciones_evento
-- Devuelve el número de puntuaciones en un evento.
-- Usado por sp_anular_puntuacion para decidir si recalcular
-- o borrar directamente el resultado_final.
-- ============================================================
CREATE FUNCTION fn_contar_puntuaciones_evento(
  p_id_competicion INT
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_count INT DEFAULT 0;
  SELECT COUNT(*) INTO v_count
    FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
   WHERE i.id_competicion = p_id_competicion;
  RETURN v_count;
END$$


-- ============================================================
-- fn_estado_competicion  (v1.2 - NUEVA)
-- Calcula el estado de una competición en tiempo real
-- comparando su fecha con la fecha actual.
-- Sustituye el campo estado eliminado de la tabla competicion.
--
-- Retorna:
--   'abierta'  → fecha futura (todavía no ha llegado)
--   'en_curso' → fecha es hoy (el evento está pasando)
--   'cerrada'  → fecha pasada (el evento ya terminó)
--   'sin_fecha'→ la competición no tiene fecha asignada
-- ============================================================
CREATE FUNCTION fn_estado_competicion(
  p_id_competicion INT
) RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_fecha DATE;

  SELECT fecha INTO v_fecha
    FROM competicion
   WHERE id_competicion = p_id_competicion;

  IF v_fecha IS NULL THEN
    RETURN 'sin_fecha';
  ELSEIF v_fecha > CURDATE() THEN
    RETURN 'abierta';
  ELSEIF v_fecha = CURDATE() THEN
    RETURN 'en_curso';
  ELSE
    RETURN 'cerrada';
  END IF;
END$$


-- ============================================================
-- fn_edad_atleta  (v1.2 - NUEVA)
-- Calcula la edad del atleta en la fecha del evento.
-- Usar la fecha del evento (no la actual) garantiza que
-- la categoría asignada es correcta para ese momento concreto.
-- ============================================================
CREATE FUNCTION fn_edad_atleta(
  p_fecha_nacimiento DATE,
  p_fecha_evento     DATE
) RETURNS INT
DETERMINISTIC
BEGIN
  RETURN TIMESTAMPDIFF(YEAR, p_fecha_nacimiento, p_fecha_evento);
END$$


-- ============================================================
-- fn_categoria_valida_para_edad  (v1.2 - NUEVA)
-- Comprueba si la edad del atleta es válida para la categoría.
-- Evita que un atleta se inscriba en una categoría incorrecta.
--
-- Rangos de edad por categoría (Classic Physique):
--   Cadete:  14 - 17 años
--   Juvenil: 18 - 23 años
--   Senior:  24+ años
--
-- Devuelve 1 si la edad es válida, 0 si no lo es.
-- ============================================================
CREATE FUNCTION fn_categoria_valida_para_edad(
  p_id_categoria INT,
  p_edad         INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_nombre_cat VARCHAR(100);

  SELECT nombre INTO v_nombre_cat
    FROM categoria WHERE id_categoria = p_id_categoria;

  RETURN CASE v_nombre_cat
    WHEN 'Cadete'  THEN (p_edad BETWEEN 14 AND 17)
    WHEN 'Juvenil' THEN (p_edad BETWEEN 18 AND 23)
    WHEN 'Senior'  THEN (p_edad >= 24)
    ELSE 1
  END;
END$$

DELIMITER ;
