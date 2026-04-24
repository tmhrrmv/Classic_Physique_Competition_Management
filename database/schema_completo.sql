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
--        EVENT limpiar_logs_antiguos, usuarios de prueba
-- v1.4 - fn_estado_competicion: 'sin_fecha' bloquea resultados
--        sp_calcular_resultados: bloquea 'abierta' y 'sin_fecha'
--        La fecha es la única fuente de verdad del estado
-- ============================================================
-- Orden de ejecución:
--   1. create_tables
--   2. triggers
--   3. functions
--   4. procedures
--   5. users_roles
--   6. insert_data
-- ============================================================

-- ============================================================
-- 01_create_tables.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Estructura inicial: tablas base
-- v1.1 - Añadido campo activo en atleta y juez
--      - Añadido campo estado en competicion
--      - Añadida tabla usuarios
--      - Añadida tabla log_procedimientos
--      - CHECK constraint en nacionalidad (ISO alpha-3)
--      - CHECK constraint en numero_dorsal >= 1
--      - Índices en ranking_final, activo, estado
-- v1.2 - Eliminado campo estado de competicion
--        (sustituido por fn_estado_competicion basada en fecha)
--      - Añadido intentos_fallidos y bloqueado_hasta en usuarios
--      - Añadido ip_origen en log_procedimientos
--      - Añadido fecha_modificacion en atleta y juez
-- v1.3 - Añadido edad_min y edad_max en categoria
--        Los rangos de edad ya no están hardcodeados en código,
--        fn_categoria_valida_para_edad los lee dinámicamente
--      - Añadidos usuarios de prueba en insert
--      - Añadido EVENT limpiar_logs_antiguos para mantenimiento
--        automático del log_procedimientos
-- ============================================================

DROP DATABASE IF EXISTS gestion_competiciones;
CREATE DATABASE gestion_competiciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE gestion_competiciones;

-- -------------------------------------------------------
-- categoria
-- v1.3: añadido edad_min y edad_max
--       Los rangos de edad se leen dinámicamente desde aquí
--       fn_categoria_valida_para_edad ya no tiene valores
--       hardcodeados, usa estas columnas
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- competicion
-- v1.2: eliminado campo estado
--       El estado se calcula dinámicamente con
--       fn_estado_competicion(fecha):
--         fecha > HOY  → abierta
--         fecha = HOY  → en_curso
--         fecha < HOY  → cerrada
-- -------------------------------------------------------
CREATE TABLE competicion (
  id_competicion INT          NOT NULL AUTO_INCREMENT,
  nombre_evento  VARCHAR(200) NOT NULL,
  fecha          DATE         DEFAULT NULL,
  lugar          VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (id_competicion),
  UNIQUE KEY uq_competicion (nombre_evento, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- atleta
-- v1.1: añadido campo activo
--       activo=1 puede inscribirse, activo=0 retirado
--       Sin borrar historial gracias al campo activo
-- v1.2: añadido fecha_modificacion (ON UPDATE automático)
-- nacionalidad: formato ISO 3166-1 alpha-3 (ESP, MEX, ARG)
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- juez
-- v1.1: añadido campo activo
--       activo=1 puede puntuar, activo=0 retirado
--       Sin borrar historial de puntuaciones anteriores
-- v1.2: añadido fecha_modificacion (ON UPDATE automático)
-- -------------------------------------------------------
CREATE TABLE juez (
  id_juez            INT          NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(200) NOT NULL,
  licencia           VARCHAR(50)  NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_modificacion DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_juez),
  UNIQUE KEY uq_juez_licencia (licencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- inscripcion
-- v1.1: numero_dorsal >= 1 (no puede ser 0 ni negativo)
--       UNIQUE (id_competicion, numero_dorsal): dorsal
--       único por evento
-- El trigger valida:
--   - atleta activo
--   - competicion no cerrada (via fn_estado_competicion)
--   - peso y estatura dentro de rangos de categoria
--   - edad del atleta válida para la categoria
--     (via fn_categoria_valida_para_edad, v1.3 dinámico)
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- puntuacion
-- ranking_otorgado >= 1 según reglamento Classic Physique
-- UNIQUE (id_inscripcion, id_juez): un juez no puede
-- puntuar dos veces al mismo atleta en el mismo evento
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- resultado_final
-- Una fila por inscripción, se recalcula con UPSERT
-- media_ranking aplica descarte de extremos del reglamento
-- sp_calcular_resultados solo puede ejecutarse si la
-- competición está cerrada (fn_estado_competicion)
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- usuarios
-- Gestiona el login del backoffice web
-- v1.1: tabla nueva
-- v1.2: añadido intentos_fallidos y bloqueado_hasta
--       para protección contra fuerza bruta
--       La lógica de bloqueo se gestiona desde PHP
-- id_juez: FK opcional, vincula usuario web con juez BD
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- log_procedimientos
-- Auditoría de llamadas a los sp_
-- v1.1: tabla nueva
-- v1.2: añadido ip_origen
--       PHP pasa $_SERVER['REMOTE_ADDR'] como parámetro
--       MySQL no puede obtener la IP por sí solo
-- v1.3: el EVENT limpiar_logs_antiguos borra registros
--       con más de 90 días automáticamente
-- -------------------------------------------------------
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

-- -------------------------------------------------------
-- Índices adicionales para consultas frecuentes
-- v1.1: añadidos idx_resultado_ranking, idx_atleta_activo
--       idx_juez_activo
-- v1.2: eliminado idx_competicion_estado (campo eliminado)
--       añadido idx_usuarios_rol
-- -------------------------------------------------------
CREATE INDEX idx_inscripcion_atleta      ON inscripcion(id_atleta);
CREATE INDEX idx_inscripcion_competicion ON inscripcion(id_competicion);
CREATE INDEX idx_puntuacion_inscripcion  ON puntuacion(id_inscripcion);
CREATE INDEX idx_resultado_competicion   ON resultado_final(id_competicion);
CREATE INDEX idx_resultado_cat_comp      ON resultado_final(id_competicion, id_categoria);
CREATE INDEX idx_resultado_ranking       ON resultado_final(ranking_final);
CREATE INDEX idx_atleta_activo           ON atleta(activo);
CREATE INDEX idx_juez_activo             ON juez(activo);
CREATE INDEX idx_usuarios_rol            ON usuarios(rol);

-- -------------------------------------------------------
-- EVENT: limpiar_logs_antiguos
-- v1.3: borra automáticamente logs con más de 90 días
--       Se ejecuta cada día a las 03:00
--       Evita que log_procedimientos crezca indefinidamente
-- Requiere: SET GLOBAL event_scheduler = ON;
-- -------------------------------------------------------
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS limpiar_logs_antiguos
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURDATE(), '03:00:00'))
DO
  DELETE FROM log_procedimientos
   WHERE fecha < DATE_SUB(NOW(), INTERVAL 90 DAY);


-- ============================================================
-- 02_triggers.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Triggers de validación de peso y estatura
-- v1.1 - Añadida validación de atleta activo
--      - Añadida validación de estado de competicion
-- v1.2 - Sustituida validación de campo estado
--        por fn_estado_competicion basada en fecha
-- ============================================================


DROP TRIGGER IF EXISTS trg_validar_inscripcion_insert;
DROP TRIGGER IF EXISTS trg_validar_inscripcion_update;

DELIMITER $$

-- ============================================================
-- trg_validar_inscripcion_insert
-- Se dispara ANTES de insertar en inscripcion.
-- Validaciones en orden:
-- 1. Atleta activo (v1.1)
-- 2. Competicion no cerrada usando fn_estado_competicion (v1.2)
--    (sustituye la validacion directa del campo estado)
-- 3. Peso no supera el máximo de la categoría
-- 4. Estatura dentro del rango de la categoría
-- ============================================================
CREATE TRIGGER trg_validar_inscripcion_insert
BEFORE INSERT ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);
  DECLARE v_activo     TINYINT(1);
  DECLARE v_estado     VARCHAR(20);

  -- v1.1: validar que el atleta está activo
  SELECT activo INTO v_activo
    FROM atleta WHERE id_atleta = NEW.id_atleta;

  IF v_activo = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El atleta está inactivo y no puede inscribirse';
  END IF;

  -- v1.2: validar estado de competicion via función
  -- fn_estado_competicion calcula el estado en tiempo real
  -- según la fecha del evento, sin necesidad de campo estado
  SET v_estado = fn_estado_competicion(NEW.id_competicion);

  IF v_estado = 'cerrada' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición está cerrada y no admite nuevas inscripciones';
  END IF;

  -- Validar peso y estatura contra los límites de la categoría
  IF NEW.id_categoria IS NOT NULL THEN

    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

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

-- ============================================================
-- trg_validar_inscripcion_update
-- Se dispara ANTES de actualizar en inscripcion.
-- Aplica las mismas validaciones de peso y estatura
-- para cuando se corrigen datos de una inscripción existente.
-- ============================================================
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
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

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
-- 03_functions.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Funciones auxiliares básicas:
--        fn_get_atleta_id, fn_inscripcion_existe,
--        fn_juez_existe, fn_competicion_existe,
--        fn_ya_inscrito, fn_contar_puntuaciones_evento
-- v1.1 - fn_juez_existe: comprueba también activo=1
--        Un juez inactivo no puede puntuar
-- v1.2 - Nueva: fn_estado_competicion
--        Calcula estado en tiempo real según la fecha
--        Sustituye el campo estado eliminado de competicion
--      - Nueva: fn_edad_atleta
--        Calcula edad del atleta en la fecha del evento
--      - Nueva: fn_categoria_valida_para_edad
--        Comprueba si la edad es válida para la categoría
-- v1.3 - fn_categoria_valida_para_edad: ya no tiene rangos
--        hardcodeados, lee edad_min y edad_max directamente
--        de la tabla categoria (más flexible y mantenible)
-- v1.4 - fn_estado_competicion: añadido comentario explícito
--        de que 'sin_fecha' bloquea el cálculo de resultados
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
-- fn_estado_competicion  (v1.2, revisada v1.4)
-- Calcula el estado de una competición en tiempo real
-- comparando su fecha con la fecha actual.
-- La fecha es la ÚNICA fuente de verdad del estado.
-- Sustituye el campo estado eliminado de la tabla competicion.
--
-- Retorna:
--   'abierta'   → fecha futura (inscripciones abiertas)
--   'en_curso'  → fecha es hoy (evento activo)
--   'cerrada'   → fecha pasada (evento finalizado)
--   'sin_fecha' → sin fecha asignada
--
-- v1.4: 'sin_fecha' bloquea sp_calcular_resultados.
--       Una competición sin fecha no puede tener resultados.
-- ============================================================
CREATE FUNCTION fn_estado_competicion(
  p_id_competicion INT
) RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_fecha DATE;
  SELECT fecha INTO v_fecha
    FROM competicion WHERE id_competicion = p_id_competicion;

  IF v_fecha IS NULL   THEN RETURN 'sin_fecha';
  ELSEIF v_fecha > CURDATE() THEN RETURN 'abierta';
  ELSEIF v_fecha = CURDATE() THEN RETURN 'en_curso';
  ELSE RETURN 'cerrada';
  END IF;
END$$


-- ============================================================
-- fn_edad_atleta  (v1.2)
-- Calcula la edad del atleta en la fecha del evento.
-- Usar la fecha del evento (no la actual) garantiza que
-- la categoría es correcta para ese momento concreto.
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
-- fn_categoria_valida_para_edad  (v1.2, mejorada v1.3)
-- Comprueba si la edad del atleta es válida para la categoría.
-- Evita que un atleta se inscriba en una categoría incorrecta.
--
-- v1.3: ya no tiene rangos hardcodeados en el código.
--       Lee edad_min y edad_max directamente de la tabla
--       categoria, por lo que si el reglamento cambia basta
--       con actualizar los datos sin tocar el código.
--
-- Devuelve 1 si la edad es válida, 0 si no lo es.
-- Si la categoría no tiene rangos de edad definidos → 1 (OK)
-- ============================================================
CREATE FUNCTION fn_categoria_valida_para_edad(
  p_id_categoria INT,
  p_edad         INT
) RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE v_edad_min INT DEFAULT NULL;
  DECLARE v_edad_max INT DEFAULT NULL;

  SELECT edad_min, edad_max
    INTO v_edad_min, v_edad_max
    FROM categoria WHERE id_categoria = p_id_categoria;

  -- Si no hay rangos definidos se permite cualquier edad
  IF v_edad_min IS NULL AND v_edad_max IS NULL THEN
    RETURN 1;
  END IF;

  -- Validar rango mínimo si está definido
  IF v_edad_min IS NOT NULL AND p_edad < v_edad_min THEN
    RETURN 0;
  END IF;

  -- Validar rango máximo si está definido (NULL = sin límite superior)
  IF v_edad_max IS NOT NULL AND p_edad > v_edad_max THEN
    RETURN 0;
  END IF;

  RETURN 1;
END$$

DELIMITER ;


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
--      - sp_inscribir_atleta: validación edad/categoría
--        usando fn_edad_atleta y fn_categoria_valida_para_edad
-- v1.3 - sp_calcular_resultados: valida que la competición
--        esté cerrada antes de calcular el podio final
--        (fn_estado_competicion debe devolver 'cerrada')
--      - sp_anular_puntuacion: eliminado p_ip del CALL
--        interno a sp_calcular_resultados, la IP ya está
--        registrada en el log del procedimiento padre
-- v1.4 - sp_calcular_resultados: bloquea también 'sin_fecha'
--        Una competición sin fecha no puede tener resultados
--        La fecha es la única fuente de verdad del estado
-- ============================================================


DROP PROCEDURE IF EXISTS sp_inscribir_atleta;
DROP PROCEDURE IF EXISTS sp_registrar_puntuacion;
DROP PROCEDURE IF EXISTS sp_calcular_resultados;
DROP PROCEDURE IF EXISTS sp_anular_puntuacion;

DELIMITER $$

-- ============================================================
-- sp_inscribir_atleta
-- Registra a un atleta en un evento con sus datos físicos.
--
-- Validaciones en orden:
-- 1. p_id_competicion no puede ser NULL
-- 2. La competición debe existir
-- 3. v1.2: edad del atleta válida para la categoría
--    usando fn_edad_atleta + fn_categoria_valida_para_edad
--    v1.3: fn_categoria_valida_para_edad lee edad_min/max
--    dinámicamente desde tabla categoria (no hardcodeado)
-- 4. Si el atleta no existe lo crea (fn_get_atleta_id)
-- 5. El atleta no puede estar ya inscrito en ese evento
-- 6. El trigger valida peso/estatura, atleta activo
--    y competición no cerrada (fn_estado_competicion)
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

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion)';
  END IF;

  IF fn_competicion_existe(p_id_competicion) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La competición indicada no existe';
  END IF;

  -- v1.2: validar edad del atleta contra la categoría
  -- v1.3: fn_categoria_valida_para_edad lee rangos
  --       dinámicamente desde tabla categoria
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

    SET p_id_atleta_out = fn_get_atleta_id(p_nombre, p_apellido, p_fecha_nacimiento);

    IF p_id_atleta_out IS NULL THEN
      INSERT INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
      VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);
      SET p_id_atleta_out = LAST_INSERT_ID();
    END IF;

    IF fn_ya_inscrito(p_id_atleta_out, p_id_competicion) = 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento';
    END IF;

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
      SET MESSAGE_TEXT = 'El juez no existe o está inactivo';
  END IF;

  START TRANSACTION;

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
-- Calcula el podio de una competición aplicando el reglamento.
--
-- Reglamento Classic Physique aplicado:
-- - 3+ jueces: descarta MAX y MIN, media de los restantes
-- - <3 jueces: media simple como fallback
-- - Ranking por categoría (Senior, Juvenil, Cadete separados)
-- - Menor media = mejor puesto (1 = campeón)
-- - UPSERT: puede ejecutarse varias veces sin duplicar
--
-- v1.1: CONNECTION_ID() evita colisiones entre sesiones
-- v1.2: añadido p_ip para log de auditoría
-- v1.3: valida que la competición esté cerrada antes de
--       calcular. No tiene sentido calcular el podio final
--       de un evento que aún no ha terminado.
--       Solo permite estados 'cerrada' o 'en_curso'.
-- v1.4: bloquea también 'sin_fecha'. La fecha es la única
--       fuente de verdad — sin fecha no hay estado válido
--       y por tanto no se pueden calcular resultados.
-- ============================================================
CREATE PROCEDURE sp_calcular_resultados(
  IN p_id_competicion INT,
  IN p_ip             VARCHAR(45)
)
BEGIN
  DECLARE v_tabla_medias  VARCHAR(60);
  DECLARE v_tabla_ranking VARCHAR(60);
  DECLARE v_estado        VARCHAR(20);
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

  -- v1.3: solo calcular si la competición está en curso o cerrada
-- v1.4: también bloquea 'sin_fecha' — la fecha es la única
--       fuente de verdad, sin fecha no hay estado válido
SET v_estado = fn_estado_competicion(p_id_competicion);
  IF v_estado = 'abierta' OR v_estado = 'sin_fecha' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Solo se pueden calcular resultados de competiciones en curso o cerradas';
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

    SET @sql = CONCAT(
      'CREATE TEMPORARY TABLE ', v_tabla_ranking, ' AS
       SELECT id_inscripcion, id_categoria, media, n_jueces,
         RANK() OVER (PARTITION BY id_categoria ORDER BY media ASC) AS ranking_final
       FROM ', v_tabla_medias);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
          CONCAT('competicion=', p_id_competicion, ' estado=', v_estado),
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
-- v1.2: añadido p_ip para log de auditoría
-- v1.3: eliminado p_ip del CALL interno a sp_calcular_resultados
--       La IP ya está registrada en el log de este procedimiento.
--       El CALL interno usa NULL como IP para evitar duplicar
--       el registro de auditoría con la misma IP.
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
      -- v1.3: NULL como IP en el CALL interno para no duplicar
      -- el registro de auditoría (ya está registrado arriba)
      CALL sp_calcular_resultados(v_id_competicion, NULL);
    ELSE
      -- Sin puntuaciones: borrar resultado directamente
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


-- ============================================================
-- 05_users_roles.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Roles y usuarios iniciales:
--        admin_bd, organizador, juez, consulta_publica
-- ============================================================


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


-- ============================================================
-- 06_insert_data.sql
-- Base de datos: gestion_competiciones (MySQL 8.0+)
-- ============================================================
-- HISTORIAL DE CAMBIOS
-- v1.0 - Datos de prueba iniciales
-- v1.3 - Añadido edad_min y edad_max en categoria
--      - Actualizado CALL sp_inscribir_atleta con p_ip
--      - Añadidos usuarios de prueba en tabla usuarios
-- ============================================================


-- -------------------------------------------------------
-- Categorías
-- v1.3: incluye edad_min y edad_max para validación dinámica
-- -------------------------------------------------------
INSERT IGNORE INTO categoria (nombre, altura_min, altura_max, peso_maximo_permitido, edad_min, edad_max)
VALUES
  ('Cadete',  1.40, 1.59,  60.00, 14, 17),
  ('Juvenil', 1.60, 1.75,  75.00, 18, 23),
  ('Senior',  1.76, 2.20, 120.00, 24, NULL);

-- -------------------------------------------------------
-- Competiciones
-- -------------------------------------------------------
INSERT IGNORE INTO competicion (nombre_evento, fecha, lugar)
VALUES
  ('Torneo Apertura 2024',       '2024-03-10', 'Estadio Nacional'),
  ('Copa Ciudad de Madrid 2024', '2024-09-21', 'Centro Deportivo Municipal'),
  ('Torneo Apertura 2025',       '2025-03-09', 'Estadio Nacional'),
  ('Copa Ciudad de Madrid 2025', '2025-09-20', 'Centro Deportivo Municipal');

-- -------------------------------------------------------
-- Jueces
-- -------------------------------------------------------
INSERT IGNORE INTO juez (nombre, licencia, activo)
VALUES
  ('Roberto Diaz',  'JUE-001', 1),
  ('Ana Martinez',  'JUE-002', 1),
  ('Pedro Sanchez', 'JUE-003', 1);

-- -------------------------------------------------------
-- Usuarios de prueba
-- v1.3: datos de prueba para el login del backoffice
-- IMPORTANTE: cambiar contraseñas en producción
-- password_hash generado con password_hash() de PHP
-- Contraseñas de prueba:
--   admin_user  → Admin2025!
--   org_user    → Org2025!
--   juez_user   → Juez2025!
--   publico     → Publico2025!
-- -------------------------------------------------------
INSERT IGNORE INTO usuarios (username, password_hash, email, rol, id_juez)
VALUES
  ('admin_user',  '$2y$10$example_hash_admin',   'admin@competicion.es',   'admin',            NULL),
  ('org_user',    '$2y$10$example_hash_org',     'org@competicion.es',     'organizador',      NULL),
  ('juez_roberto','$2y$10$example_hash_juez1',   'roberto@competicion.es', 'juez',
    (SELECT id_juez FROM juez WHERE licencia = 'JUE-001')),
  ('publico',     '$2y$10$example_hash_publico', 'publico@competicion.es', 'consulta_publica', NULL);

-- -------------------------------------------------------
-- Inscripciones por evento
-- v1.3: CALL actualizado con parámetro p_ip
--       Se usa '127.0.0.1' como IP de prueba para los datos semilla
-- -------------------------------------------------------

-- === TORNEO APERTURA 2024 ===
CALL sp_inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  101, 55.50, 1.62, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  102, 62.00, 1.68, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  103, 85.00, 1.80, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  104, 90.00, 1.82, '127.0.0.1', @id_a, @id_i);

-- === COPA CIUDAD DE MADRID 2024 ===
CALL sp_inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  201, 56.00, 1.62, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  202, 86.50, 1.80, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  203, 90.50, 1.82, '127.0.0.1', @id_a, @id_i);

-- === TORNEO APERTURA 2025 ===
CALL sp_inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  101, 63.00, 1.62, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  102, 60.50, 1.68, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Sofia',  'Lopez',    '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  103, 58.00, 1.65, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  104, 87.00, 1.80, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  105, 91.00, 1.82, '127.0.0.1', @id_a, @id_i);

-- === COPA CIUDAD DE MADRID 2025 ===
CALL sp_inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  201, 63.50, 1.62, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Sofia',  'Lopez',    '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  202, 58.50, 1.65, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  203, 61.00, 1.68, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  204, 87.50, 1.80, '127.0.0.1', @id_a, @id_i);

CALL sp_inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  205, 91.50, 1.82, '127.0.0.1', @id_a, @id_i);

-- -------------------------------------------------------
-- Puntuaciones de prueba
-- -------------------------------------------------------
INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 1
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento IN ('Torneo Apertura 2024','Copa Ciudad de Madrid 2024',
                          'Torneo Apertura 2025','Copa Ciudad de Madrid 2025');

-- -------------------------------------------------------
-- Verificación rápida
-- -------------------------------------------------------
SELECT 'categoria'   AS tabla, COUNT(*) AS total FROM categoria
UNION ALL SELECT 'competicion',  COUNT(*) FROM competicion
UNION ALL SELECT 'atleta',       COUNT(*) FROM atleta
UNION ALL SELECT 'juez',         COUNT(*) FROM juez
UNION ALL SELECT 'inscripcion',  COUNT(*) FROM inscripcion
UNION ALL SELECT 'puntuacion',   COUNT(*) FROM puntuacion
UNION ALL SELECT 'usuarios',     COUNT(*) FROM usuarios;
