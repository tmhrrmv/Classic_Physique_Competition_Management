-- ============================================================
-- schema_completo.sql  (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Estructura inicial
-- v1.1 - activo en atleta y juez, tabla usuarios,
--        tabla log_procedimientos, CHECK constraints
-- v1.2 - Eliminado campo estado de competicion,
--        fn_estado_competicion basada en fecha,
--        fn_edad_atleta, fn_categoria_valida_para_edad,
--        p_ip en todos los sp_, ip_origen en log
-- v1.3 - edad_min/max en categoria (rangos dinámicos),
--        sp_calcular_resultados valida estado,
--        EVENT limpiar_logs_antiguos
-- v1.4 - fn_estado_competicion: 'sin_fecha' bloquea resultados
--        sp_calcular_resultados: bloquea 'abierta' y 'sin_fecha'
-- v1.5 - Eliminados inserts de prueba del schema
--        Solo se insertan categorias, jueces y usuarios reales
-- ============================================================

DROP DATABASE IF EXISTS gestion_competiciones;
CREATE DATABASE gestion_competiciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE gestion_competiciones;

-- ============================================================
-- TABLAS
-- ============================================================

CREATE TABLE categoria (
  id_categoria          INT           NOT NULL AUTO_INCREMENT,
  nombre                VARCHAR(100)  NOT NULL,
  altura_min            DECIMAL(5,2)  DEFAULT NULL,
  altura_max            DECIMAL(5,2)  DEFAULT NULL,
  peso_maximo_permitido DECIMAL(6,2)  DEFAULT NULL,
  edad_min              INT           DEFAULT NULL,
  edad_max              INT           DEFAULT NULL,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE competicion (
  id_competicion INT          NOT NULL AUTO_INCREMENT,
  nombre_evento  VARCHAR(200) NOT NULL,
  fecha          DATE         DEFAULT NULL,
  lugar          VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (id_competicion),
  UNIQUE KEY uq_competicion (nombre_evento, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE atleta (
  id_atleta          INT          NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(100) NOT NULL,
  apellido           VARCHAR(100) NOT NULL,
  fecha_nacimiento   DATE         NOT NULL,
  nacionalidad       VARCHAR(3)   DEFAULT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_modificacion DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_atleta),
  UNIQUE KEY uq_atleta (nombre, apellido, fecha_nacimiento),
  CONSTRAINT chk_nacionalidad CHECK (nacionalidad REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE juez (
  id_juez            INT          NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(200) NOT NULL,
  licencia           VARCHAR(50)  NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_modificacion DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_juez),
  UNIQUE KEY uq_juez_licencia (licencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE inscripcion (
  id_inscripcion    INT          NOT NULL AUTO_INCREMENT,
  id_atleta         INT          NOT NULL,
  id_competicion    INT          NOT NULL,
  id_categoria      INT          DEFAULT NULL,
  numero_dorsal     INT          DEFAULT NULL,
  peso_registro     DECIMAL(6,2) DEFAULT NULL,
  estatura_registro DECIMAL(5,2) DEFAULT NULL,
  fecha_inscripcion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_inscripcion),
  UNIQUE KEY uq_atleta_evento (id_atleta, id_competicion),
  UNIQUE KEY uq_dorsal_evento (id_competicion, numero_dorsal),
  CONSTRAINT chk_dorsal_positivo
    CHECK (numero_dorsal IS NULL OR numero_dorsal >= 1),
  CONSTRAINT fk_insc_atleta      FOREIGN KEY (id_atleta)      REFERENCES atleta(id_atleta)           ON DELETE CASCADE,
  CONSTRAINT fk_insc_competicion FOREIGN KEY (id_competicion) REFERENCES competicion(id_competicion) ON DELETE CASCADE,
  CONSTRAINT fk_insc_categoria   FOREIGN KEY (id_categoria)   REFERENCES categoria(id_categoria)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE puntuacion (
  id_puntuacion    INT NOT NULL AUTO_INCREMENT,
  id_inscripcion   INT NOT NULL,
  id_juez          INT NOT NULL,
  ranking_otorgado INT DEFAULT NULL,
  PRIMARY KEY (id_puntuacion),
  UNIQUE KEY uq_puntuacion (id_inscripcion, id_juez),
  CONSTRAINT chk_ranking_positivo
    CHECK (ranking_otorgado IS NULL OR ranking_otorgado >= 1),
  CONSTRAINT fk_punt_inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripcion(id_inscripcion) ON DELETE CASCADE,
  CONSTRAINT fk_punt_juez        FOREIGN KEY (id_juez)        REFERENCES juez(id_juez)               ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE resultado_final (
  id_resultado   INT           NOT NULL AUTO_INCREMENT,
  id_inscripcion INT           NOT NULL,
  id_competicion INT           NOT NULL,
  id_categoria   INT           DEFAULT NULL,
  ranking_final  INT           DEFAULT NULL,
  media_ranking  DECIMAL(5,2)  DEFAULT NULL,
  num_jueces     INT           DEFAULT NULL,
  fecha_calculo  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_resultado),
  UNIQUE KEY uq_resultado_inscripcion (id_inscripcion),
  CONSTRAINT chk_ranking_final_positivo
    CHECK (ranking_final IS NULL OR ranking_final >= 1),
  CONSTRAINT fk_res_inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripcion(id_inscripcion) ON DELETE CASCADE,
  CONSTRAINT fk_res_competicion FOREIGN KEY (id_competicion) REFERENCES competicion(id_competicion) ON DELETE CASCADE,
  CONSTRAINT fk_res_categoria   FOREIGN KEY (id_categoria)   REFERENCES categoria(id_categoria)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE usuarios (
  id_usuario        INT          NOT NULL AUTO_INCREMENT,
  username          VARCHAR(50)  NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  email             VARCHAR(150) DEFAULT NULL,
  rol               ENUM('admin','organizador','juez','consulta_publica') NOT NULL DEFAULT 'consulta_publica',
  id_juez           INT          DEFAULT NULL,
  activo            TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos INT          NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME     DEFAULT NULL,
  fecha_creacion    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso     DATETIME     DEFAULT NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email    (email),
  CONSTRAINT fk_usuario_juez FOREIGN KEY (id_juez) REFERENCES juez(id_juez) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE log_procedimientos (
  id_log        INT           NOT NULL AUTO_INCREMENT,
  procedimiento VARCHAR(100)  NOT NULL,
  usuario       VARCHAR(50)   DEFAULT NULL,
  ip_origen     VARCHAR(45)   DEFAULT NULL,
  parametros    TEXT          DEFAULT NULL,
  resultado     ENUM('ok','error') NOT NULL,
  mensaje       TEXT          DEFAULT NULL,
  fecha         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  INDEX idx_log_procedimiento (procedimiento),
  INDEX idx_log_fecha         (fecha),
  INDEX idx_log_resultado     (resultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE INDEX idx_inscripcion_atleta      ON inscripcion(id_atleta);
CREATE INDEX idx_inscripcion_competicion ON inscripcion(id_competicion);
CREATE INDEX idx_puntuacion_inscripcion  ON puntuacion(id_inscripcion);
CREATE INDEX idx_resultado_competicion   ON resultado_final(id_competicion);
CREATE INDEX idx_resultado_cat_comp      ON resultado_final(id_competicion, id_categoria);
CREATE INDEX idx_resultado_ranking       ON resultado_final(ranking_final);
CREATE INDEX idx_atleta_activo           ON atleta(activo);
CREATE INDEX idx_juez_activo             ON juez(activo);
CREATE INDEX idx_usuarios_rol            ON usuarios(rol);

SET GLOBAL event_scheduler = ON;
CREATE EVENT IF NOT EXISTS limpiar_logs_antiguos
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURDATE(), '03:00:00'))
DO
  DELETE FROM log_procedimientos
   WHERE fecha < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- ============================================================
-- TRIGGERS
-- ============================================================

DROP TRIGGER IF EXISTS trg_validar_inscripcion_insert;
DROP TRIGGER IF EXISTS trg_validar_inscripcion_update;

DELIMITER $$

CREATE TRIGGER trg_validar_inscripcion_insert
BEFORE INSERT ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);
  DECLARE v_activo     TINYINT(1);
  DECLARE v_estado     VARCHAR(20);

  SELECT activo INTO v_activo FROM atleta WHERE id_atleta = NEW.id_atleta;
  IF v_activo = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El atleta está inactivo y no puede inscribirse';
  END IF;

  SET v_estado = fn_estado_competicion(NEW.id_competicion);
  IF v_estado = 'cerrada' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición está cerrada y no admite nuevas inscripciones';
  END IF;

  IF NEW.id_categoria IS NOT NULL THEN
    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;
    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;
    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_validar_inscripcion_update
BEFORE UPDATE ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);

  IF NEW.id_categoria IS NOT NULL THEN
    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;
    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;
    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- FUNCTIONS
-- ============================================================

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

CREATE FUNCTION fn_get_atleta_id(
  p_nombre VARCHAR(100), p_apellido VARCHAR(100), p_fecha_nacimiento DATE
) RETURNS INT READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v_id INT DEFAULT NULL;
  SELECT id_atleta INTO v_id FROM atleta
   WHERE nombre=p_nombre AND apellido=p_apellido AND fecha_nacimiento=p_fecha_nacimiento LIMIT 1;
  RETURN v_id;
END$$

CREATE FUNCTION fn_inscripcion_existe(p_id_inscripcion INT)
RETURNS TINYINT(1) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM inscripcion WHERE id_inscripcion=p_id_inscripcion;
  RETURN v;
END$$

CREATE FUNCTION fn_juez_existe(p_id_juez INT)
RETURNS TINYINT(1) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM juez WHERE id_juez=p_id_juez AND activo=1;
  RETURN v;
END$$

CREATE FUNCTION fn_competicion_existe(p_id_competicion INT)
RETURNS TINYINT(1) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM competicion WHERE id_competicion=p_id_competicion;
  RETURN v;
END$$

CREATE FUNCTION fn_ya_inscrito(p_id_atleta INT, p_id_competicion INT)
RETURNS TINYINT(1) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM inscripcion
   WHERE id_atleta=p_id_atleta AND id_competicion=p_id_competicion;
  RETURN v > 0;
END$$

CREATE FUNCTION fn_contar_puntuaciones_evento(p_id_competicion INT)
RETURNS INT READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM puntuacion p
    JOIN inscripcion i ON i.id_inscripcion=p.id_inscripcion
   WHERE i.id_competicion=p_id_competicion;
  RETURN v;
END$$

CREATE FUNCTION fn_estado_competicion(p_id_competicion INT)
RETURNS VARCHAR(20) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v_fecha DATE;
  SELECT fecha INTO v_fecha FROM competicion WHERE id_competicion=p_id_competicion;
  IF v_fecha IS NULL        THEN RETURN 'sin_fecha';
  ELSEIF v_fecha > CURDATE() THEN RETURN 'abierta';
  ELSEIF v_fecha = CURDATE() THEN RETURN 'en_curso';
  ELSE                           RETURN 'cerrada';
  END IF;
END$$

CREATE FUNCTION fn_edad_atleta(p_fecha_nacimiento DATE, p_fecha_evento DATE)
RETURNS INT DETERMINISTIC
BEGIN
  RETURN TIMESTAMPDIFF(YEAR, p_fecha_nacimiento, p_fecha_evento);
END$$

CREATE FUNCTION fn_categoria_valida_para_edad(p_id_categoria INT, p_edad INT)
RETURNS TINYINT(1) READS SQL DATA DETERMINISTIC
BEGIN
  DECLARE v_min INT DEFAULT NULL;
  DECLARE v_max INT DEFAULT NULL;
  SELECT edad_min, edad_max INTO v_min, v_max FROM categoria WHERE id_categoria=p_id_categoria;
  IF v_min IS NULL AND v_max IS NULL THEN RETURN 1; END IF;
  IF v_min IS NOT NULL AND p_edad < v_min THEN RETURN 0; END IF;
  IF v_max IS NOT NULL AND p_edad > v_max THEN RETURN 0; END IF;
  RETURN 1;
END$$

DELIMITER ;

-- ============================================================
-- PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_inscribir_atleta;
DROP PROCEDURE IF EXISTS sp_registrar_puntuacion;
DROP PROCEDURE IF EXISTS sp_calcular_resultados;
DROP PROCEDURE IF EXISTS sp_anular_puntuacion;

DELIMITER $$

CREATE PROCEDURE sp_inscribir_atleta(
  IN p_nombre VARCHAR(100), IN p_apellido VARCHAR(100),
  IN p_fecha_nacimiento DATE, IN p_nacionalidad VARCHAR(3),
  IN p_id_competicion INT, IN p_id_categoria INT,
  IN p_numero_dorsal INT, IN p_peso_registro DECIMAL(6,2),
  IN p_estatura_registro DECIMAL(5,2), IN p_ip VARCHAR(45),
  OUT p_id_atleta_out INT, OUT p_id_inscripcion_out INT
)
BEGIN
  DECLARE v_mensaje VARCHAR(255);
  DECLARE v_fecha_evento DATE;
  DECLARE v_edad INT;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
    VALUES ('sp_inscribir_atleta',p_ip,CONCAT('nombre=',IFNULL(p_nombre,'NULL'),' comp=',IFNULL(p_id_competicion,'NULL')),'error',v_mensaje);
    RESIGNAL;
  END;

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion)';
  END IF;
  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;
  IF p_id_categoria IS NOT NULL THEN
    SELECT fecha INTO v_fecha_evento FROM competicion WHERE id_competicion=p_id_competicion;
    SET v_edad = fn_edad_atleta(p_fecha_nacimiento, v_fecha_evento);
    IF fn_categoria_valida_para_edad(p_id_categoria, v_edad) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La edad del atleta no es válida para la categoría seleccionada';
    END IF;
  END IF;

  START TRANSACTION;
    SET p_id_atleta_out = fn_get_atleta_id(p_nombre, p_apellido, p_fecha_nacimiento);
    IF p_id_atleta_out IS NULL THEN
      INSERT INTO atleta (nombre,apellido,fecha_nacimiento,nacionalidad)
      VALUES (p_nombre,p_apellido,p_fecha_nacimiento,p_nacionalidad);
      SET p_id_atleta_out = LAST_INSERT_ID();
    END IF;
    IF fn_ya_inscrito(p_id_atleta_out, p_id_competicion) = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento';
    END IF;
    INSERT INTO inscripcion (id_atleta,id_competicion,id_categoria,numero_dorsal,peso_registro,estatura_registro)
    VALUES (p_id_atleta_out,p_id_competicion,p_id_categoria,p_numero_dorsal,p_peso_registro,p_estatura_registro);
    SET p_id_inscripcion_out = LAST_INSERT_ID();
  COMMIT;

  INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
  VALUES ('sp_inscribir_atleta',p_ip,CONCAT('atleta=',p_id_atleta_out,' insc=',p_id_inscripcion_out),'ok','Inscripción creada correctamente');
  SELECT p_id_atleta_out AS id_atleta, p_id_inscripcion_out AS id_inscripcion, 'Inscripción creada correctamente' AS mensaje;
END$$

CREATE PROCEDURE sp_registrar_puntuacion(
  IN p_id_inscripcion INT, IN p_id_juez INT,
  IN p_ranking INT, IN p_ip VARCHAR(45),
  OUT p_id_puntuacion_out INT
)
BEGIN
  DECLARE v_mensaje VARCHAR(255);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
    VALUES ('sp_registrar_puntuacion',p_ip,CONCAT('insc=',IFNULL(p_id_inscripcion,'NULL'),' juez=',IFNULL(p_id_juez,'NULL')),'error',v_mensaje);
    RESIGNAL;
  END;

  IF p_ranking IS NULL OR p_ranking < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El ranking debe ser un número positivo mayor o igual a 1';
  END IF;
  IF fn_inscripcion_existe(p_id_inscripcion) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La inscripción indicada no existe';
  END IF;
  IF fn_juez_existe(p_id_juez) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El juez no existe o está inactivo';
  END IF;

  START TRANSACTION;
    INSERT INTO puntuacion (id_inscripcion,id_juez,ranking_otorgado) VALUES (p_id_inscripcion,p_id_juez,p_ranking);
    SET p_id_puntuacion_out = LAST_INSERT_ID();
  COMMIT;

  INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
  VALUES ('sp_registrar_puntuacion',p_ip,CONCAT('insc=',p_id_inscripcion,' juez=',p_id_juez,' ranking=',p_ranking),'ok','Puntuación registrada correctamente');
  SELECT p_id_puntuacion_out AS id_puntuacion, 'Puntuación registrada correctamente' AS mensaje;
END$$

CREATE PROCEDURE sp_calcular_resultados(IN p_id_competicion INT, IN p_ip VARCHAR(45))
BEGIN
  DECLARE v_tabla_medias  VARCHAR(60);
  DECLARE v_tabla_ranking VARCHAR(60);
  DECLARE v_estado        VARCHAR(20);
  DECLARE v_mensaje       VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
    VALUES ('sp_calcular_resultados',p_ip,CONCAT('comp=',IFNULL(p_id_competicion,'NULL')),'error',v_mensaje);
    RESIGNAL;
  END;

  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;
  SET v_estado = fn_estado_competicion(p_id_competicion);
  IF v_estado = 'abierta' OR v_estado = 'sin_fecha' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se pueden calcular resultados de competiciones en curso o cerradas';
  END IF;
  IF fn_contar_puntuaciones_evento(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay puntuaciones registradas para esta competición';
  END IF;

  SET v_tabla_medias  = CONCAT('tmp_medias_',  CONNECTION_ID());
  SET v_tabla_ranking = CONCAT('tmp_ranking_', CONNECTION_ID());

  START TRANSACTION;
    SET @sql = CONCAT('CREATE TEMPORARY TABLE ',v_tabla_medias,' AS
      SELECT i.id_inscripcion, i.id_categoria,
        COUNT(p.id_puntuacion) AS n_jueces,
        CASE WHEN COUNT(p.id_puntuacion)>=3
          THEN ROUND((SUM(p.ranking_otorgado)-MAX(p.ranking_otorgado)-MIN(p.ranking_otorgado))/(COUNT(p.id_puntuacion)-2),2)
          ELSE ROUND(AVG(p.ranking_otorgado),2) END AS media
      FROM inscripcion i JOIN puntuacion p ON p.id_inscripcion=i.id_inscripcion
      WHERE i.id_competicion=',p_id_competicion,' AND p.ranking_otorgado IS NOT NULL
      GROUP BY i.id_inscripcion, i.id_categoria');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('CREATE TEMPORARY TABLE ',v_tabla_ranking,' AS
      SELECT id_inscripcion, id_categoria, media, n_jueces,
        RANK() OVER (PARTITION BY id_categoria ORDER BY media ASC) AS ranking_final
      FROM ',v_tabla_medias);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('INSERT INTO resultado_final (id_inscripcion,id_competicion,id_categoria,ranking_final,media_ranking,num_jueces)
      SELECT r.id_inscripcion,',p_id_competicion,',r.id_categoria,r.ranking_final,r.media,r.n_jueces
      FROM ',v_tabla_ranking,' r
      ON DUPLICATE KEY UPDATE ranking_final=VALUES(ranking_final),media_ranking=VALUES(media_ranking),num_jueces=VALUES(num_jueces),fecha_calculo=CURRENT_TIMESTAMP');
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

    SET @d1=CONCAT('DROP TEMPORARY TABLE IF EXISTS ',v_tabla_ranking);
    SET @d2=CONCAT('DROP TEMPORARY TABLE IF EXISTS ',v_tabla_medias);
    PREPARE stmt FROM @d1; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    PREPARE stmt FROM @d2; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  COMMIT;

  INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
  VALUES ('sp_calcular_resultados',p_ip,CONCAT('comp=',p_id_competicion,' estado=',v_estado),'ok','Resultados calculados correctamente');

  SELECT cat.nombre AS categoria, rf.ranking_final AS puesto,
         CONCAT(a.nombre,' ',a.apellido) AS atleta, a.nacionalidad,
         rf.media_ranking, rf.num_jueces
  FROM resultado_final rf
  JOIN inscripcion i ON i.id_inscripcion=rf.id_inscripcion
  JOIN atleta      a ON a.id_atleta=i.id_atleta
  LEFT JOIN categoria cat ON cat.id_categoria=rf.id_categoria
  WHERE rf.id_competicion=p_id_competicion
  ORDER BY cat.nombre, rf.ranking_final;
END$$

CREATE PROCEDURE sp_anular_puntuacion(IN p_id_puntuacion INT, IN p_ip VARCHAR(45), OUT p_filas INT)
BEGIN
  DECLARE v_id_comp INT;
  DECLARE v_id_insc INT;
  DECLARE v_restantes INT DEFAULT 0;
  DECLARE v_mensaje VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    GET DIAGNOSTICS CONDITION 1 v_mensaje = MESSAGE_TEXT;
    INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
    VALUES ('sp_anular_puntuacion',p_ip,CONCAT('id_punt=',IFNULL(p_id_puntuacion,'NULL')),'error',v_mensaje);
    RESIGNAL;
  END;

  IF NOT EXISTS (SELECT 1 FROM puntuacion WHERE id_puntuacion=p_id_puntuacion) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La puntuación indicada no existe';
  END IF;

  SELECT p.id_inscripcion, i.id_competicion INTO v_id_insc, v_id_comp
    FROM puntuacion p JOIN inscripcion i ON i.id_inscripcion=p.id_inscripcion
   WHERE p.id_puntuacion=p_id_puntuacion;

  START TRANSACTION;
    DELETE FROM puntuacion WHERE id_puntuacion=p_id_puntuacion;
    SET p_filas = ROW_COUNT();
    SET v_restantes = fn_contar_puntuaciones_evento(v_id_comp);
    IF v_restantes > 0 THEN
      CALL sp_calcular_resultados(v_id_comp, NULL);
    ELSE
      DELETE FROM resultado_final WHERE id_competicion=v_id_comp;
    END IF;
  COMMIT;

  INSERT INTO log_procedimientos (procedimiento,ip_origen,parametros,resultado,mensaje)
  VALUES ('sp_anular_puntuacion',p_ip,CONCAT('id_punt=',p_id_puntuacion,' comp=',v_id_comp),'ok','Puntuación anulada y resultados actualizados');
  SELECT p_filas AS filas_afectadas, 'Puntuación anulada y resultados actualizados' AS mensaje;
END$$

DELIMITER ;

-- ============================================================
-- ROLES Y USUARIOS MYSQL
-- ============================================================

CREATE ROLE IF NOT EXISTS 'admin_bd';
CREATE ROLE IF NOT EXISTS 'organizador';
CREATE ROLE IF NOT EXISTS 'juez';
CREATE ROLE IF NOT EXISTS 'consulta_publica';

GRANT ALL PRIVILEGES ON gestion_competiciones.* TO 'admin_bd';
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.categoria       TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.competicion     TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.atleta          TO 'organizador';
GRANT ALL PRIVILEGES         ON gestion_competiciones.inscripcion     TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.puntuacion      TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.juez            TO 'organizador';
GRANT SELECT                 ON gestion_competiciones.resultado_final TO 'organizador';
GRANT SELECT ON gestion_competiciones.categoria      TO 'juez';
GRANT SELECT ON gestion_competiciones.competicion    TO 'juez';
GRANT SELECT (id_inscripcion,id_competicion,id_categoria) ON gestion_competiciones.inscripcion TO 'juez';
GRANT SELECT, INSERT, UPDATE ON gestion_competiciones.puntuacion TO 'juez';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'juez';
GRANT SELECT (id_atleta,nombre,apellido,nacionalidad) ON gestion_competiciones.atleta TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.competicion    TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.categoria      TO 'consulta_publica';
GRANT SELECT (id_inscripcion,id_competicion,id_categoria,numero_dorsal) ON gestion_competiciones.inscripcion TO 'consulta_publica';
GRANT SELECT ON gestion_competiciones.resultado_final TO 'consulta_publica';

FLUSH PRIVILEGES;

-- ============================================================
-- DATOS BASE (sin inscripciones ni puntuaciones de prueba)
-- ============================================================

INSERT IGNORE INTO categoria (nombre, altura_min, altura_max, peso_maximo_permitido, edad_min, edad_max) VALUES
  ('Cadete',  1.40, 1.59,  60.00, 14, 17),
  ('Juvenil', 1.60, 1.75,  75.00, 18, 23),
  ('Senior',  1.76, 2.20, 120.00, 24, NULL);

INSERT IGNORE INTO juez (nombre, licencia, activo) VALUES
  ('Roberto Diaz',  'JUE-001', 1),
  ('Ana Martinez',  'JUE-002', 1),
  ('Pedro Sanchez', 'JUE-003', 1);

-- Usuarios con hashes reales generados con password_hash() de PHP
-- admin_user   → Admin2025!
-- org_user     → Org2025!
-- juez_roberto → Juez2025!
-- publico      → Publico2025!
INSERT IGNORE INTO usuarios (username, password_hash, email, rol, id_juez) VALUES
  ('admin_user',   '$2y$10$o/kc4G0.V3H3k99F64bDr.m5uSxXst5wBSxakVWUi6.LxfrviVSv2', 'admin@competicion.es',   'admin',            NULL),
  ('org_user',     '$2y$10$zdqzsfN2bRgOTpZzodiZ2uOJYUDYXJQW2dBGKpfCoJ2IEvBHwCyKm', 'org@competicion.es',     'organizador',      NULL),
  ('juez_roberto', '$2y$10$mNL8a20SGQF238FZ9e8q9eV2iPbmN2kO9bNo7lDhozZyoVXGdhFie', 'roberto@competicion.es', 'juez',
    (SELECT id_juez FROM juez WHERE licencia = 'JUE-001')),
  ('publico',      '$2y$10$cAZEl/b69OJzonNiA/CO..MvJ29ykKVngsQLOYEHCrXJP3eAKMSoy', 'publico@competicion.es', 'consulta_publica', NULL);

-- Verificación
SELECT 'categoria' AS tabla, COUNT(*) AS total FROM categoria
UNION ALL SELECT 'juez',     COUNT(*) FROM juez
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios;
