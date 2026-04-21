-- ============================================================
-- ARCHIVO 3: functions.sql
-- Orden de ejecución: 3° (después de triggers.sql)
-- Base de datos: MySQL
-- ============================================================

USE gestion_competiciones;

DROP PROCEDURE IF EXISTS inscribir_atleta;
DROP PROCEDURE IF EXISTS actualizar_datos_inscripcion;
DROP PROCEDURE IF EXISTS historial_atleta;
DROP PROCEDURE IF EXISTS atletas_sin_inscripcion_en_evento;

DELIMITER $$

-- -------------------------------------------------------
-- PROCEDURE: inscribir_atleta
--
-- Registra a un atleta en un evento con sus datos físicos
-- actuales (peso, estatura, categoría).
--
--   - Si el atleta no existe -> lo crea.
--   - Si ya está inscrito en ESE evento -> error claro.
--   - Si ya estuvo en OTROS eventos -> crea nueva inscripción
--     con los datos físicos nuevos (pueden ser distintos).
--
-- El trigger trg_validar_inscripcion_insert valida
-- automáticamente peso/estatura vs categoría.
-- -------------------------------------------------------
CREATE PROCEDURE inscribir_atleta(
  IN p_nombre             VARCHAR(100),
  IN p_apellido           VARCHAR(100),
  IN p_fecha_nacimiento   DATE,
  IN p_nacionalidad       VARCHAR(10),
  IN p_id_competicion     INT,
  IN p_id_categoria       INT,
  IN p_numero_dorsal      INT,
  IN p_peso_registro      DECIMAL(6,2),
  IN p_estatura_registro  DECIMAL(5,2)
)
BEGIN
  DECLARE v_id_atleta      INT DEFAULT NULL;
  DECLARE v_id_inscripcion INT DEFAULT NULL;
  DECLARE v_ya_inscrito    INT DEFAULT 0;

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion) al inscribir un atleta';
  END IF;

  -- Crear atleta si no existe; si ya existe, ignorar
  INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
  VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);

  SELECT id_atleta INTO v_id_atleta
    FROM atleta
   WHERE nombre           = p_nombre
     AND apellido         = p_apellido
     AND fecha_nacimiento = p_fecha_nacimiento
   LIMIT 1;

  -- Verificar si ya está inscrito en ESTE evento
  SELECT COUNT(*) INTO v_ya_inscrito
    FROM inscripcion
   WHERE id_atleta      = v_id_atleta
     AND id_competicion = p_id_competicion;

  IF v_ya_inscrito > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento. Usa actualizar_datos_inscripcion() para corregir sus datos.';
  END IF;

  -- Crear inscripción con los datos físicos actuales
  -- (el trigger validará peso/estatura vs categoría)
  INSERT INTO inscripcion
    (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
  VALUES
    (v_id_atleta, p_id_competicion, p_id_categoria, p_numero_dorsal, p_peso_registro, p_estatura_registro);

  SET v_id_inscripcion = LAST_INSERT_ID();

  SELECT
    v_id_atleta      AS out_id_atleta,
    v_id_inscripcion AS out_id_inscripcion,
    'Inscripción creada correctamente' AS mensaje;
END$$


-- -------------------------------------------------------
-- PROCEDURE: actualizar_datos_inscripcion
--
-- Corrige los datos físicos de una inscripción ya existente.
-- Usa COALESCE para actualizar solo los campos que se pasen.
-- -------------------------------------------------------
CREATE PROCEDURE actualizar_datos_inscripcion(
  IN p_id_inscripcion     INT,
  IN p_id_categoria       INT,
  IN p_numero_dorsal      INT,
  IN p_peso_registro      DECIMAL(6,2),
  IN p_estatura_registro  DECIMAL(5,2)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM inscripcion WHERE id_inscripcion = p_id_inscripcion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No existe una inscripción con ese id';
  END IF;

  -- El trigger trg_validar_inscripcion_update validará los nuevos datos
  UPDATE inscripcion SET
    id_categoria      = COALESCE(p_id_categoria,      id_categoria),
    numero_dorsal     = COALESCE(p_numero_dorsal,     numero_dorsal),
    peso_registro     = COALESCE(p_peso_registro,     peso_registro),
    estatura_registro = COALESCE(p_estatura_registro, estatura_registro)
  WHERE id_inscripcion = p_id_inscripcion;

  SELECT 'Inscripción actualizada correctamente' AS mensaje, ROW_COUNT() AS filas_afectadas;
END$$


-- -------------------------------------------------------
-- PROCEDURE: historial_atleta
--
-- Muestra el historial completo de un atleta en todos los
-- eventos en que ha participado, con los datos físicos
-- que tenía en cada momento.
-- -------------------------------------------------------
CREATE PROCEDURE historial_atleta(
  IN p_id_atleta INT
)
BEGIN
  SELECT
    a.nombre                   AS nombre_atleta,
    a.apellido                 AS apellido_atleta,
    c.nombre_evento,
    c.fecha                    AS fecha_evento,
    cat.nombre                 AS categoria,
    i.numero_dorsal,
    i.peso_registro            AS peso_en_ese_evento,
    i.estatura_registro        AS estatura_en_ese_evento,
    i.fecha_inscripcion
  FROM inscripcion i
  JOIN atleta      a   ON a.id_atleta      = i.id_atleta
  JOIN competicion c   ON c.id_competicion = i.id_competicion
  LEFT JOIN categoria cat ON cat.id_categoria = i.id_categoria
  WHERE i.id_atleta = p_id_atleta
  ORDER BY c.fecha;
END$$


-- -------------------------------------------------------
-- PROCEDURE: atletas_sin_inscripcion_en_evento
--
-- Lista atletas que NO están inscritos en un evento concreto.
-- -------------------------------------------------------
CREATE PROCEDURE atletas_sin_inscripcion_en_evento(
  IN p_id_competicion INT
)
BEGIN
  SELECT
    a.id_atleta,
    a.nombre,
    a.apellido,
    a.fecha_nacimiento,
    a.nacionalidad
  FROM atleta a
  WHERE NOT EXISTS (
    SELECT 1 FROM inscripcion i
     WHERE i.id_atleta      = a.id_atleta
       AND i.id_competicion = p_id_competicion
  )
  ORDER BY a.apellido, a.nombre;
END$$

DELIMITER ;
