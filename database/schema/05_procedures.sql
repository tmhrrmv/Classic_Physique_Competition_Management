USE gestion_competiciones;

DROP PROCEDURE IF EXISTS sp_inscribir_atleta;
DROP PROCEDURE IF EXISTS sp_registrar_puntuacion;
DROP PROCEDURE IF EXISTS sp_calcular_resultados;
DROP PROCEDURE IF EXISTS sp_anular_puntuacion;

DELIMITER $$

-- ============================================================
-- sp_inscribir_atleta
-- Crea el atleta si no existe e inscribe en el evento.
-- El trigger valida peso/estatura contra la categoría.
-- ============================================================
CREATE PROCEDURE sp_inscribir_atleta(
  IN  p_nombre             VARCHAR(100),
  IN  p_apellido           VARCHAR(100),
  IN  p_fecha_nacimiento   DATE,
  IN  p_nacionalidad       VARCHAR(3),
  IN  p_id_competicion     INT,
  IN  p_id_categoria       INT,
  IN  p_numero_dorsal      INT,
  IN  p_peso_registro      DECIMAL(6,2),
  IN  p_estatura_registro  DECIMAL(5,2),
  OUT p_id_atleta_out      INT,
  OUT p_id_inscripcion_out INT
)
BEGIN
  DECLARE v_ya_inscrito INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion)';
  END IF;

  START TRANSACTION;

    INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
    VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);

    SELECT id_atleta INTO p_id_atleta_out
      FROM atleta
     WHERE nombre           = p_nombre
       AND apellido         = p_apellido
       AND fecha_nacimiento = p_fecha_nacimiento
     LIMIT 1;

    SELECT COUNT(*) INTO v_ya_inscrito
      FROM inscripcion
     WHERE id_atleta      = p_id_atleta_out
       AND id_competicion = p_id_competicion;

    IF v_ya_inscrito > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento';
    END IF;

    INSERT INTO inscripcion
      (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
    VALUES
      (p_id_atleta_out, p_id_competicion, p_id_categoria, p_numero_dorsal, p_peso_registro, p_estatura_registro);

    SET p_id_inscripcion_out = LAST_INSERT_ID();

  COMMIT;

  SELECT p_id_atleta_out      AS id_atleta,
         p_id_inscripcion_out AS id_inscripcion,
         'Inscripción creada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_registrar_puntuacion
-- Un juez registra su ranking para un atleta en un evento.
-- Valida existencia de inscripción y juez, evita duplicados.
-- ============================================================
CREATE PROCEDURE sp_registrar_puntuacion(
  IN  p_id_inscripcion   INT,
  IN  p_id_juez          INT,
  IN  p_ranking          INT,
  OUT p_id_puntuacion_out INT
)
BEGIN
  DECLARE v_existe_inscripcion INT DEFAULT 0;
  DECLARE v_existe_juez        INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_ranking IS NULL OR p_ranking < 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El ranking debe ser un número positivo mayor o igual a 1';
  END IF;

  SELECT COUNT(*) INTO v_existe_inscripcion
    FROM inscripcion WHERE id_inscripcion = p_id_inscripcion;

  IF v_existe_inscripcion = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La inscripción indicada no existe';
  END IF;

  SELECT COUNT(*) INTO v_existe_juez
    FROM juez WHERE id_juez = p_id_juez;

  IF v_existe_juez = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El juez indicado no existe';
  END IF;

  START TRANSACTION;

    INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
    VALUES (p_id_inscripcion, p_id_juez, p_ranking);

    SET p_id_puntuacion_out = LAST_INSERT_ID();

  COMMIT;

  SELECT p_id_puntuacion_out AS id_puntuacion,
         'Puntuación registrada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_calcular_resultados
-- Calcula el podio de una competición aplicando el reglamento:
-- descarta la nota más alta y más baja de cada atleta
-- si hay 3 o más jueces, luego calcula la media y ordena
-- por categoría. Puede ejecutarse varias veces (UPSERT).
-- ============================================================
CREATE PROCEDURE sp_calcular_resultados(
  IN p_id_competicion INT
)
BEGIN
  DECLARE v_existe INT DEFAULT 0;
  DECLARE v_tiene_puntuaciones INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SELECT COUNT(*) INTO v_existe
    FROM competicion WHERE id_competicion = p_id_competicion;

  IF v_existe = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;

  SELECT COUNT(*) INTO v_tiene_puntuaciones
    FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
   WHERE i.id_competicion = p_id_competicion;

  IF v_tiene_puntuaciones = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No hay puntuaciones registradas para esta competición';
  END IF;

  START TRANSACTION;

    DROP TEMPORARY TABLE IF EXISTS tmp_medias;
    CREATE TEMPORARY TABLE tmp_medias AS
    SELECT
      i.id_inscripcion,
      i.id_categoria,
      COUNT(p.id_puntuacion) AS n_jueces,
      CASE
        WHEN COUNT(p.id_puntuacion) >= 3
          THEN ROUND(
                 (SUM(p.ranking_otorgado) - MAX(p.ranking_otorgado) - MIN(p.ranking_otorgado))
                 / (COUNT(p.id_puntuacion) - 2), 2)
        ELSE ROUND(AVG(p.ranking_otorgado), 2)
      END AS media
    FROM inscripcion i
    JOIN puntuacion  p ON p.id_inscripcion = i.id_inscripcion
    WHERE i.id_competicion   = p_id_competicion
      AND p.ranking_otorgado IS NOT NULL
    GROUP BY i.id_inscripcion, i.id_categoria;

    DROP TEMPORARY TABLE IF EXISTS tmp_ranking;
    CREATE TEMPORARY TABLE tmp_ranking AS
    SELECT
      id_inscripcion,
      id_categoria,
      media,
      n_jueces,
      RANK() OVER (PARTITION BY id_categoria ORDER BY media ASC) AS ranking_final
    FROM tmp_medias;

    INSERT INTO resultado_final
      (id_inscripcion, id_competicion, id_categoria, ranking_final, media_ranking, num_jueces)
    SELECT
      r.id_inscripcion, p_id_competicion, r.id_categoria,
      r.ranking_final, r.media, r.n_jueces
    FROM tmp_ranking r
    ON DUPLICATE KEY UPDATE
      ranking_final  = VALUES(ranking_final),
      media_ranking  = VALUES(media_ranking),
      num_jueces     = VALUES(num_jueces),
      fecha_calculo  = CURRENT_TIMESTAMP;

    DROP TEMPORARY TABLE IF EXISTS tmp_ranking;
    DROP TEMPORARY TABLE IF EXISTS tmp_medias;

  COMMIT;

  SELECT
    cat.nombre                        AS categoria,
    rf.ranking_final                  AS puesto,
    CONCAT(a.nombre, ' ', a.apellido) AS atleta,
    a.nacionalidad,
    rf.media_ranking,
    rf.num_jueces
  FROM resultado_final rf
  JOIN inscripcion i ON i.id_inscripcion = rf.id_inscripcion
  JOIN atleta      a ON a.id_atleta      = i.id_atleta
  LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
  WHERE rf.id_competicion = p_id_competicion
  ORDER BY cat.nombre, rf.ranking_final;
END$$


-- ============================================================
-- sp_anular_puntuacion
-- Elimina una puntuación incorrecta y recalcula el podio.
-- Si el recálculo falla hace ROLLBACK restaurando la puntuación.
-- ============================================================
CREATE PROCEDURE sp_anular_puntuacion(
  IN  p_id_puntuacion   INT,
  OUT p_filas_afectadas INT
)
BEGIN
  DECLARE v_id_inscripcion INT;
  DECLARE v_id_competicion INT;
  DECLARE v_existe         INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SELECT COUNT(*) INTO v_existe
    FROM puntuacion WHERE id_puntuacion = p_id_puntuacion;

  IF v_existe = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La puntuación indicada no existe';
  END IF;

  SELECT p.id_inscripcion, i.id_competicion
    INTO v_id_inscripcion, v_id_competicion
    FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
   WHERE p.id_puntuacion = p_id_puntuacion;

  START TRANSACTION;

    DELETE FROM puntuacion WHERE id_puntuacion = p_id_puntuacion;
    SET p_filas_afectadas = ROW_COUNT();

    CALL sp_calcular_resultados(v_id_competicion);

  COMMIT;

  SELECT p_filas_afectadas AS filas_afectadas,
         'Puntuación anulada y resultados recalculados' AS mensaje;
END$$

DELIMITER ;
