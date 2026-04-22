USE gestion_competiciones;

DROP FUNCTION IF EXISTS fn_get_atleta_id;
DROP FUNCTION IF EXISTS fn_inscripcion_existe;
DROP FUNCTION IF EXISTS fn_juez_existe;
DROP FUNCTION IF EXISTS fn_competicion_existe;
DROP FUNCTION IF EXISTS fn_ya_inscrito;
DROP FUNCTION IF EXISTS fn_contar_puntuaciones_evento;

DELIMITER $$

-- ============================================================
-- fn_get_atleta_id
-- Devuelve el id_atleta si existe, NULL si no existe.
-- Fusiona fn_atleta_existe y la búsqueda de ID en una sola
-- consulta, evitando dos roundtrips a la BD.
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
    FROM inscripcion
   WHERE id_inscripcion = p_id_inscripcion;

  RETURN v_existe;
END$$


-- ============================================================
-- fn_juez_existe
-- Devuelve 1 si el juez existe, 0 si no.
-- ============================================================
CREATE FUNCTION fn_juez_existe(
  p_id_juez INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_existe TINYINT(1) DEFAULT 0;

  SELECT COUNT(*) INTO v_existe
    FROM juez
   WHERE id_juez = p_id_juez;

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
    FROM competicion
   WHERE id_competicion = p_id_competicion;

  RETURN v_existe;
END$$


-- ============================================================
-- fn_ya_inscrito
-- Devuelve 1 si el atleta ya está inscrito en ese evento.
-- Separada de fn_get_atleta_id para mantener responsabilidad
-- única: una función busca el ID, otra comprueba inscripción.
-- ============================================================
CREATE FUNCTION fn_ya_inscrito(
  p_id_atleta      INT,
  p_id_competicion INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_existe TINYINT(1) DEFAULT 0;

  SELECT COUNT(*) INTO v_existe
    FROM inscripcion
   WHERE id_atleta      = p_id_atleta
     AND id_competicion = p_id_competicion;

  RETURN v_existe;
END$$


-- ============================================================
-- fn_contar_puntuaciones_evento
-- Devuelve el número de puntuaciones registradas en un evento.
-- Usada por sp_anular_puntuacion para decidir si recalcular
-- o borrar directamente el resultado (mejora 3).
-- ============================================================
CREATE FUNCTION fn_contar_puntuaciones_evento(
  p_id_competicion INT
) RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_total INT DEFAULT 0;

  SELECT COUNT(*) INTO v_total
    FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
   WHERE i.id_competicion = p_id_competicion
     AND p.ranking_otorgado IS NOT NULL;

  RETURN v_total;
END$$

DELIMITER ;
