-- ============================================================
-- 04_procedures.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Procedures base:
--        sp_inscribir_atleta, sp_registrar_puntuacion,
--        sp_calcular_resultados, sp_anular_puntuacion
-- v1.1 - Añadido log_procedimientos en todos los sp_
--      - sp_calcular_resultados: CONNECTION_ID() para
--        evitar colisiones entre sesiones concurrentes
--      - sp_anular_puntuacion: gestión de 0 puntuaciones
--        restantes sin intentar recalcular
-- v1.2 - Añadido parámetro p_ip VARCHAR(45) a todos los sp_
--        PHP pasa $_SERVER['REMOTE_ADDR'] como valor
--      - sp_inscribir_atleta: validación de edad del atleta
--        contra la categoría usando fn_edad_atleta y
--        fn_categoria_valida_para_edad
-- ============================================================

USE gestion_competiciones;

DROP PROCEDURE IF EXISTS sp_inscribir_atleta;
DROP PROCEDURE IF EXISTS sp_registrar_puntuacion;
DROP PROCEDURE IF EXISTS sp_calcular_resultados;
DROP PROCEDURE IF EXISTS sp_anular_puntuacion;

DELIMITER $$

-- ============================================================
-- sp_inscribir_atleta
-- Registra a un atleta en un evento con sus datos físicos.
--
-- Validaciones:
-- 1. p_id_competicion no puede ser NULL
-- 2. La competición debe existir
-- 3. Si no existe el atleta lo crea (fn_get_atleta_id)
-- 4. El atleta no puede estar ya inscrito en ese evento
-- 5. v1.2: edad del atleta válida para la categoría
--    (fn_edad_atleta + fn_categoria_valida_para_edad)
-- 6. El trigger valida peso/estatura y atleta activo
--
-- v1.2: añadido p_ip para log de auditoría
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
  IN  p_ip                 VARCHAR(45),
  OUT p_id_atleta_out      INT,
  OUT p_id_inscripcion_out INT
)
BEGIN
  DECLARE v_mensaje      VARCHAR(255);
  DECLARE v_fecha_evento DATE;
  DECLARE v_edad         INT;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
    VALUES ('sp_inscribir_atleta', p_ip,
            CONCAT('nombre=', IFNULL(p_nombre,'NULL'),
                   ' competicion=', IFNULL(p_id_competicion,'NULL')),
            'error', v_mensaje);
    RESIGNAL;
  END;

  -- Validar evento obligatorio
  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion)';
  END IF;

  -- Validar que la competición existe
  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;

  -- v1.2: validar edad del atleta contra la categoría
  IF p_id_categoria IS NOT NULL THEN
    SELECT fecha INTO v_fecha_evento
      FROM competicion WHERE id_competicion = p_id_competicion;

    SET v_edad = fn_edad_atleta(p_fecha_nacimiento, v_fecha_evento);

    IF fn_categoria_valida_para_edad(p_id_categoria, v_edad) = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La edad del atleta no es válida para la categoría seleccionada';
    END IF;
  END IF;

  START TRANSACTION;

    -- Crear atleta si no existe, reutilizar si ya existe
    SET p_id_atleta_out = fn_get_atleta_id(p_nombre, p_apellido, p_fecha_nacimiento);

    IF p_id_atleta_out IS NULL THEN
      INSERT INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
      VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);
      SET p_id_atleta_out = LAST_INSERT_ID();
    END IF;

    -- Validar doble inscripción en el mismo evento
    IF fn_ya_inscrito(p_id_atleta_out, p_id_competicion) = 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento';
    END IF;

    -- Insertar inscripción (el trigger valida peso, estatura y atleta activo)
    INSERT INTO inscripcion
      (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
    VALUES
      (p_id_atleta_out, p_id_competicion, p_id_categoria,
       p_numero_dorsal, p_peso_registro, p_estatura_registro);

    SET p_id_inscripcion_out = LAST_INSERT_ID();

  COMMIT;

  INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
  VALUES ('sp_inscribir_atleta', p_ip,
          CONCAT('atleta=', p_id_atleta_out, ' inscripcion=', p_id_inscripcion_out),
          'ok', 'Inscripción creada correctamente');

  SELECT p_id_atleta_out      AS id_atleta,
         p_id_inscripcion_out AS id_inscripcion,
         'Inscripción creada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_registrar_puntuacion
-- Un juez registra su ranking para un atleta en un evento.
--
-- Reglamento Classic Physique:
-- - ranking >= 1 (1 = mejor posición)
-- - Un juez no puede puntuar dos veces al mismo atleta
-- - Solo jueces activos pueden puntuar (fn_juez_existe v1.1)
--
-- v1.2: añadido p_ip para log de auditoría
-- ============================================================
CREATE PROCEDURE sp_registrar_puntuacion(
  IN  p_id_inscripcion    INT,
  IN  p_id_juez           INT,
  IN  p_ranking           INT,
  IN  p_ip                VARCHAR(45),
  OUT p_id_puntuacion_out INT
)
BEGIN
  DECLARE v_mensaje VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
    VALUES ('sp_registrar_puntuacion', p_ip,
            CONCAT('inscripcion=', IFNULL(p_id_inscripcion,'NULL'),
                   ' juez=', IFNULL(p_id_juez,'NULL')),
            'error', v_mensaje);
    RESIGNAL;
  END;

  -- Validar ranking según reglamento: mínimo 1
  IF p_ranking IS NULL OR p_ranking < 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El ranking debe ser un número positivo mayor o igual a 1';
  END IF;

  -- Validar existencia de inscripción
  IF fn_inscripcion_existe(p_id_inscripcion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La inscripción indicada no existe';
  END IF;

  -- Validar juez existe y está activo (v1.1)
  IF fn_juez_existe(p_id_juez) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El juez no existe o está inactivo';
  END IF;

  START TRANSACTION;

    -- UNIQUE KEY uq_puntuacion garantiza no duplicados
    INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
    VALUES (p_id_inscripcion, p_id_juez, p_ranking);

    SET p_id_puntuacion_out = LAST_INSERT_ID();

  COMMIT;

  INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
  VALUES ('sp_registrar_puntuacion', p_ip,
          CONCAT('inscripcion=', p_id_inscripcion,
                 ' juez=', p_id_juez, ' ranking=', p_ranking),
          'ok', 'Puntuación registrada correctamente');

  SELECT p_id_puntuacion_out AS id_puntuacion,
         'Puntuación registrada correctamente' AS mensaje;
END$$


-- ============================================================
-- sp_calcular_resultados
-- Calcula el podio de una competición aplicando el reglamento:
--
-- Reglamento Classic Physique aplicado:
-- - 3+ jueces: descarta MAX y MIN de cada atleta,
--   calcula media de los restantes
-- - <3 jueces: media simple como fallback
-- - Ranking por categoría (Senior, Juvenil, Cadete separados)
-- - Menor media = mejor puesto (1 = campeón)
-- - UPSERT: puede ejecutarse varias veces sin duplicar
--
-- v1.1: CONNECTION_ID() evita colisiones entre sesiones
-- v1.2: añadido p_ip para log de auditoría
-- ============================================================
CREATE PROCEDURE sp_calcular_resultados(
  IN p_id_competicion INT,
  IN p_ip             VARCHAR(45)
)
BEGIN
  DECLARE v_tabla_medias  VARCHAR(60);
  DECLARE v_tabla_ranking VARCHAR(60);
  DECLARE v_mensaje       VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
    VALUES ('sp_calcular_resultados', p_ip,
            CONCAT('competicion=', IFNULL(p_id_competicion,'NULL')),
            'error', v_mensaje);
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

  -- v1.1: nombres únicos con CONNECTION_ID() para evitar
  -- colisiones si dos usuarios calculan simultáneamente
  SET v_tabla_medias  = CONCAT('tmp_medias_',  CONNECTION_ID());
  SET v_tabla_ranking = CONCAT('tmp_ranking_', CONNECTION_ID());

  START TRANSACTION;

    -- Paso 1: media ajustada por atleta y categoría
    SET @sql = CONCAT(
      'CREATE TEMPORARY TABLE ', v_tabla_medias, ' AS
       SELECT i.id_inscripcion, i.id_categoria,
         COUNT(p.id_puntuacion) AS n_jueces,
         CASE
           WHEN COUNT(p.id_puntuacion) >= 3
             THEN ROUND((SUM(p.ranking_otorgado) - MAX(p.ranking_otorgado) - MIN(p.ranking_otorgado))
                        / (COUNT(p.id_puntuacion) - 2), 2)
           ELSE ROUND(AVG(p.ranking_otorgado), 2)
         END AS media
       FROM inscripcion i
       JOIN puntuacion p ON p.id_inscripcion = i.id_inscripcion
       WHERE i.id_competicion = ', p_id_competicion, '
         AND p.ranking_otorgado IS NOT NULL
       GROUP BY i.id_inscripcion, i.id_categoria');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    -- Paso 2: ranking por categoría (menor media = mejor puesto)
    SET @sql = CONCAT(
      'CREATE TEMPORARY TABLE ', v_tabla_ranking, ' AS
       SELECT id_inscripcion, id_categoria, media, n_jueces,
         RANK() OVER (PARTITION BY id_categoria ORDER BY media ASC) AS ranking_final
       FROM ', v_tabla_medias);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    -- Paso 3: UPSERT en resultado_final
    SET @sql = CONCAT(
      'INSERT INTO resultado_final
         (id_inscripcion, id_competicion, id_categoria, ranking_final, media_ranking, num_jueces)
       SELECT r.id_inscripcion, ', p_id_competicion, ', r.id_categoria,
         r.ranking_final, r.media, r.n_jueces
       FROM ', v_tabla_ranking, ' r
       ON DUPLICATE KEY UPDATE
         ranking_final = VALUES(ranking_final),
         media_ranking = VALUES(media_ranking),
         num_jueces    = VALUES(num_jueces),
         fecha_calculo = CURRENT_TIMESTAMP');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @d1 = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_ranking);
    SET @d2 = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', v_tabla_medias);
    PREPARE stmt FROM @d1; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    PREPARE stmt FROM @d2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

  COMMIT;

  INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
  VALUES ('sp_calcular_resultados', p_ip,
          CONCAT('competicion=', p_id_competicion),
          'ok', 'Resultados calculados correctamente');

  SELECT cat.nombre AS categoria, rf.ranking_final AS puesto,
         CONCAT(a.nombre, ' ', a.apellido) AS atleta,
         a.nacionalidad, rf.media_ranking, rf.num_jueces
  FROM resultado_final rf
  JOIN inscripcion i ON i.id_inscripcion = rf.id_inscripcion
  JOIN atleta      a ON a.id_atleta      = i.id_atleta
  LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
  WHERE rf.id_competicion = p_id_competicion
  ORDER BY cat.nombre, rf.ranking_final;
END$$


-- ============================================================
-- sp_anular_puntuacion
-- Elimina una puntuación incorrecta y actualiza el podio.
--
-- v1.1: gestión de 0 puntuaciones restantes:
--   - Si quedan puntuaciones → recalcula normalmente
--   - Si NO quedan → borra resultado_final directamente
--     (evita error en sp_calcular_resultados sin datos)
-- v1.2: añadido p_ip para log de auditoría
-- ============================================================
CREATE PROCEDURE sp_anular_puntuacion(
  IN  p_id_puntuacion   INT,
  IN  p_ip              VARCHAR(45),
  OUT p_filas_afectadas INT
)
BEGIN
  DECLARE v_id_competicion         INT;
  DECLARE v_id_inscripcion         INT;
  DECLARE v_puntuaciones_restantes INT DEFAULT 0;
  DECLARE v_mensaje                VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
    VALUES ('sp_anular_puntuacion', p_ip,
            CONCAT('id_puntuacion=', IFNULL(p_id_puntuacion,'NULL')),
            'error', v_mensaje);
    RESIGNAL;
  END;

  IF NOT EXISTS (SELECT 1 FROM puntuacion WHERE id_puntuacion = p_id_puntuacion) THEN
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

    SET v_puntuaciones_restantes = fn_contar_puntuaciones_evento(v_id_competicion);

    IF v_puntuaciones_restantes > 0 THEN
      -- Quedan puntuaciones: recalcular el podio
      CALL sp_calcular_resultados(v_id_competicion, p_ip);
    ELSE
      -- Sin puntuaciones: borrar resultado directamente
      -- No tiene sentido recalcular con 0 datos
      DELETE FROM resultado_final WHERE id_competicion = v_id_competicion;
    END IF;

  COMMIT;

  INSERT INTO log_procedimientos (procedimiento, ip_origen, parametros, resultado, mensaje)
  VALUES ('sp_anular_puntuacion', p_ip,
          CONCAT('id_puntuacion=', p_id_puntuacion,
                 ' competicion=', v_id_competicion),
          'ok', 'Puntuación anulada y resultados actualizados');

  SELECT p_filas_afectadas AS filas_afectadas,
         'Puntuación anulada y resultados actualizados' AS mensaje;
END$$

DELIMITER ;
