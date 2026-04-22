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
-- ============================================================

DROP DATABASE IF EXISTS gestion_competiciones;
CREATE DATABASE gestion_competiciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE gestion_competiciones;

-- -------------------------------------------------------
-- categoria
-- rangos de peso y estatura por categoría competitiva
-- -------------------------------------------------------
CREATE TABLE categoria (
  id_categoria          INT           NOT NULL AUTO_INCREMENT,
  nombre                VARCHAR(100)  NOT NULL,
  altura_min            DECIMAL(5,2)  DEFAULT NULL,
  altura_max            DECIMAL(5,2)  DEFAULT NULL,
  peso_maximo_permitido DECIMAL(6,2)  DEFAULT NULL,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- competicion
-- v1.2: eliminado campo estado, ahora se calcula
--       dinámicamente con fn_estado_competicion(fecha)
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
--       (sin borrar historial gracias a activo)
-- nacionalidad: formato ISO 3166-1 alpha-3 (ESP, MEX, ARG)
-- -------------------------------------------------------
CREATE TABLE atleta (
  id_atleta        INT          NOT NULL AUTO_INCREMENT,
  nombre           VARCHAR(100) NOT NULL,
  apellido         VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE         NOT NULL,
  nacionalidad     VARCHAR(3)   DEFAULT NULL,
  activo           TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_modificacion DATETIME   DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_atleta),
  UNIQUE KEY uq_atleta (nombre, apellido, fecha_nacimiento),
  CONSTRAINT chk_nacionalidad CHECK (nacionalidad REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- juez
-- v1.1: añadido campo activo
--       activo=1 puede puntuar, activo=0 retirado
--       (sin borrar historial de puntuaciones anteriores)
-- -------------------------------------------------------
CREATE TABLE juez (
  id_juez  INT          NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(200) NOT NULL,
  licencia VARCHAR(50)  NOT NULL,
  activo   TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_modificacion DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_juez),
  UNIQUE KEY uq_juez_licencia (licencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- inscripcion
-- v1.1: numero_dorsal >= 1 (no puede ser 0 ni negativo)
--       UNIQUE (id_competicion, numero_dorsal): dorsal único
--       por evento
-- El trigger valida:
--   - atleta activo
--   - competicion no cerrada (via fn_estado_competicion)
--   - peso y estatura dentro de rangos de categoría
--   - edad del atleta válida para la categoría
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
-- Un juez no puede puntuar dos veces al mismo atleta
-- en el mismo evento (UNIQUE uq_puntuacion)
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
--       idx_juez_activo, idx_competicion_estado
-- v1.2: eliminado idx_competicion_estado (campo eliminado)
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
