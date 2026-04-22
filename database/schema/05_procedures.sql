USE gestion_competiciones;

DROP PROCEDURE IF EXISTS sp_inscribir_atleta;
DROP PROCEDURE IF EXISTS sp_registrar_puntuacion;
DROP PROCEDURE IF EXISTS sp_calcular_resultados;
DROP PROCEDURE IF EXISTS sp_anular_puntuacion;

DELIMITER $$

-- ============================================================
-- sp_inscribir_atleta
-- Crea el atleta si no existe e inscribe en el evento.
-- Usa fn_get_atleta_id y fn_ya_inscrito para validaciones.
-- El trigger trg_validar_inscripcion_insert valida
-- peso y estatura contra los límites de la categoría.
-- Reglamento: cada evento registra datos físicos propios
-- porque el atleta puede cambiar de categoría entre eventos.
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
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_inscribir_atleta', 'ERROR', 'Fallo al inscribir atleta');
    RESIGNAL;
  END;

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion)';
  END IF;

  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;

  START TRANSACTION;

    INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
    VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);

    SET p_id_atleta_out = fn_get_atleta_id(p_nombre, p_apellido, p_fecha_nacimiento);

    IF fn_ya_inscrito(p_id_atleta_out, p_id_competicion) = 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento';
    END IF;

    INSERT INTO inscripcion
      (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
    VALUES
      (p_id_atleta_out, p_id_competicion, p_id_categoria, p_numero_dorsal, p_peso_registro, p_estatura_registro);

    SET p_id_inscripcion_out = LAST_INSERT_ID();

    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_inscribir_atleta', 'OK',
            CONCAT('Atleta ', p_id_atleta_out, ' inscrito en competicion ', p_id_competicion));

  COMMIT;

  SELECT p_id_atleta_out      AS id_atleta,
         p_id_inscripcion_out AS id_inscripcion,
         'Inscripción creada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_registrar_puntuacion
-- Un juez registra su ranking para un atleta en un evento.
-- Reglamento: un juez no puede empatar dos atletas (UNIQUE).
-- El ranking representa la posición: 1 = mejor puesto.
-- Usa fn_inscripcion_existe y fn_juez_existe para validar.
-- ============================================================
CREATE PROCEDURE sp_registrar_puntuacion(
  IN  p_id_inscripcion    INT,
  IN  p_id_juez           INT,
  IN  p_ranking           INT,
  OUT p_id_puntuacion_out INT
)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_registrar_puntuacion', 'ERROR', 'Fallo al registrar puntuación');
    RESIGNAL;
  END;

  IF p_ranking IS NULL OR p_ranking < 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El ranking debe ser un número positivo mayor o igual a 1';
  END IF;

  IF fn_inscripcion_existe(p_id_inscripcion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La inscripción indicada no existe';
  END IF;

  IF fn_juez_existe(p_id_juez) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El juez indicado no existe';
  END IF;

  START TRANSACTION;

    INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
    VALUES (p_id_inscripcion, p_id_juez, p_ranking);

    SET p_id_puntuacion_out = LAST_INSERT_ID();

    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_registrar_puntuacion', 'OK',
            CONCAT('Juez ', p_id_juez, ' puntuó inscripcion ', p_id_inscripcion, ' con ranking ', p_ranking));

  COMMIT;

  SELECT p_id_puntuacion_out AS id_puntuacion,
         'Puntuación registrada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_calcular_resultados
-- Calcula el podio de una competición aplicando el reglamento:
--
-- REGLAMENTO APLICADO:
--   1. Si hay 3 o más jueces: descartar la nota más alta
--      (peor posición) y la más baja (mejor posición) de
--      cada atleta antes de calcular la media. Esto elimina
--      sesgos de jueces demasiado estrictos o favorables.
--   2. Si hay menos de 3 jueces: media simple (fallback).
--   3. Menor media = mejor puesto (1º el de media más baja).
--   4. Ranking independiente por categoría: Senior, Juvenil
--      y Cadete nunca compiten entre sí.
--
-- Usa CONNECTION_ID() para evitar colisión de tablas
-- temporales entre conexiones simultáneas (mejora 2).
-- Puede ejecutarse varias veces: hace UPSERT (mejora).
-- ============================================================
CREATE PROCEDURE sp_calcular_resultados(
  IN p_id_competicion INT
)
BEGIN
  DECLARE v_tabla_medias  VARCHAR(50);
  DECLARE v_tabla_ranking VARCHAR(50);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_calcular_resultados', 'ERROR',
            CONCAT('Fallo al calcular resultados competicion ', p_id_competicion));
    RESIGNAL;
  END;

  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;

  IF fn_contar_puntuaciones_evento(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No hay puntuaciones registradas para esta competición';
  END IF;

  -- Nombres únicos por conexión para evitar colisiones (mejora 2)
  SET v_tabla_medias  = CONCAT('tmp_medias_',  CONNECTION_ID());
  SET v_tabla_ranking = CONCAT('tmp_ranking_', CONNECTION_ID());

  START TRANSACTION;

    SET @sql = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_medias);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT(
      'CREATE TEMPORARY TABLE ', v_tabla_medias, ' AS ',
      'SELECT i.id_inscripcion, i.id_categoria, ',
      'COUNT(p.id_puntuacion) AS n_jueces, ',
      'CASE ',
        'WHEN COUNT(p.id_puntuacion) >= 3 ',
          'THEN ROUND((SUM(p.ranking_otorgado) - MAX(p.ranking_otorgado) - MIN(p.ranking_otorgado)) ',
               '/ (COUNT(p.id_puntuacion) - 2), 2) ',
        'ELSE ROUND(AVG(p.ranking_otorgado), 2) ',
      'END AS media ',
      'FROM inscripcion i ',
      'JOIN puntuacion p ON p.id_inscripcion = i.id_inscripcion ',
      'WHERE i.id_competicion = ', p_id_competicion, ' ',
      'AND p.ranking_otorgado IS NOT NULL ',
      'GROUP BY i.id_inscripcion, i.id_categoria'
    );
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_ranking);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT(
      'CREATE TEMPORARY TABLE ', v_tabla_ranking, ' AS ',
      'SELECT id_inscripcion, id_categoria, media, n_jueces, ',
      'RANK() OVER (PARTITION BY id_categoria ORDER BY media ASC) AS ranking_final ',
      'FROM ', v_tabla_medias
    );
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT(
      'INSERT INTO resultado_final ',
      '(id_inscripcion, id_competicion, id_categoria, ranking_final, media_ranking, num_jueces) ',
      'SELECT r.id_inscripcion, ', p_id_competicion, ', r.id_categoria, ',
      'r.ranking_final, r.media, r.n_jueces ',
      'FROM ', v_tabla_ranking, ' r ',
      'ON DUPLICATE KEY UPDATE ',
      'ranking_final = VALUES(ranking_final), ',
      'media_ranking = VALUES(media_ranking), ',
      'num_jueces    = VALUES(num_jueces), ',
      'fecha_calculo = CURRENT_TIMESTAMP'
    );
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_ranking);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_medias);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_calcular_resultados', 'OK',
            CONCAT('Resultados calculados para competicion ', p_id_competicion));

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
-- Elimina una puntuación incorrecta y gestiona el resultado:
--
-- MEJORA 3 APLICADA:
--   Si quedan puntuaciones tras el borrado → recalcular podio.
--   Si NO quedan puntuaciones → borrar resultado_final
--   directamente sin intentar recalcular (evitaría error).
--
-- Si el recálculo falla → ROLLBACK restaura la puntuación.
-- ============================================================
CREATE PROCEDURE sp_anular_puntuacion(
  IN  p_id_puntuacion   INT,
  OUT p_filas_afectadas INT
)
BEGIN
  DECLARE v_id_inscripcion  INT;
  DECLARE v_id_competicion  INT;
  DECLARE v_id_atleta       INT;
  DECLARE v_puntuaciones    INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_anular_puntuacion', 'ERROR',
            CONCAT('Fallo al anular puntuación ', p_id_puntuacion));
    RESIGNAL;
  END;

  IF fn_inscripcion_existe(p_id_puntuacion) = 0 THEN
    SELECT COUNT(*) INTO v_id_inscripcion
      FROM puntuacion WHERE id_puntuacion = p_id_puntuacion;
    IF v_id_inscripcion = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La puntuación indicada no existe';
    END IF;
  END IF;

  SELECT p.id_inscripcion, i.id_competicion, i.id_atleta
    INTO v_id_inscripcion, v_id_competicion, v_id_atleta
    FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
   WHERE p.id_puntuacion = p_id_puntuacion;

  START TRANSACTION;

    DELETE FROM puntuacion WHERE id_puntuacion = p_id_puntuacion;
    SET p_filas_afectadas = ROW_COUNT();

    -- Contar puntuaciones restantes en el evento tras el borrado
    SET v_puntuaciones = fn_contar_puntuaciones_evento(v_id_competicion);

    IF v_puntuaciones > 0 THEN
      -- Quedan puntuaciones: recalcular el podio normalmente
      CALL sp_calcular_resultados(v_id_competicion);
    ELSE
      -- No quedan puntuaciones: borrar resultado directamente
      -- sin intentar recalcular (evita error por datos vacíos)
      DELETE FROM resultado_final
       WHERE id_inscripcion = v_id_inscripcion;
    END IF;

    INSERT INTO log_procedimientos (procedimiento, resultado, mensaje)
    VALUES ('sp_anular_puntuacion', 'OK',
            CONCAT('Puntuación ', p_id_puntuacion, ' anulada. Puntuaciones restantes: ', v_puntuaciones));

  COMMIT;

  SELECT p_filas_afectadas AS filas_afectadas,
         'Puntuación anulada correctamente' AS mensaje;
END$$

DELIMITER ;
