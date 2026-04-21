-- ============================================================
-- ARCHIVO 1: create_tables.sql
-- Orden de ejecución: 1°
-- Base de datos: MySQL
-- ============================================================

-- PASO 0: Crear y seleccionar la base de datos
DROP DATABASE IF EXISTS gestion_competiciones;
CREATE DATABASE gestion_competiciones
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_spanish_ci;

USE gestion_competiciones;

-- -------------------------------------------------------
-- categoria: rangos válidos por categoría de competición
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
-- competicion: cada evento es un registro independiente
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
-- atleta: datos de identidad fijos (no cambian entre eventos)
-- peso, estatura y categoría se registran por inscripción
-- -------------------------------------------------------
CREATE TABLE atleta (
  id_atleta        INT          NOT NULL AUTO_INCREMENT,
  nombre           VARCHAR(100) NOT NULL,
  apellido         VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE         NOT NULL,
  nacionalidad     VARCHAR(10)  DEFAULT NULL,
  PRIMARY KEY (id_atleta),
  UNIQUE KEY uq_atleta (nombre, apellido, fecha_nacimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- juez
-- -------------------------------------------------------
CREATE TABLE juez (
  id_juez  INT          NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(200) NOT NULL,
  licencia VARCHAR(50)  NOT NULL,
  PRIMARY KEY (id_juez),
  UNIQUE KEY uq_juez_licencia (licencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- inscripcion: una fila por (atleta x evento)
--
--   * peso_registro y estatura_registro se toman en el
--     momento de inscribirse en ese evento concreto.
--   * id_categoria puede cambiar entre eventos.
--   * numero_dorsal se asigna por evento.
--   * UNIQUE (id_atleta, id_competicion) evita duplicados
--     dentro del mismo evento, pero permite al mismo atleta
--     aparecer en N eventos distintos con datos nuevos.
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
  CONSTRAINT fk_insc_atleta      FOREIGN KEY (id_atleta)      REFERENCES atleta(id_atleta)           ON DELETE CASCADE,
  CONSTRAINT fk_insc_competicion FOREIGN KEY (id_competicion) REFERENCES competicion(id_competicion) ON DELETE CASCADE,
  CONSTRAINT fk_insc_categoria   FOREIGN KEY (id_categoria)   REFERENCES categoria(id_categoria)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- -------------------------------------------------------
-- puntuacion: nota de un juez sobre una inscripción concreta
-- -------------------------------------------------------
CREATE TABLE puntuacion (
  id_puntuacion    INT NOT NULL AUTO_INCREMENT,
  id_inscripcion   INT NOT NULL,
  id_juez          INT NOT NULL,
  ranking_otorgado INT DEFAULT NULL,
  PRIMARY KEY (id_puntuacion),
  UNIQUE KEY uq_puntuacion (id_inscripcion, id_juez),
  CONSTRAINT fk_punt_inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripcion(id_inscripcion) ON DELETE CASCADE,
  CONSTRAINT fk_punt_juez        FOREIGN KEY (id_juez)        REFERENCES juez(id_juez)               ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Índices para consultas frecuentes
CREATE INDEX idx_inscripcion_atleta      ON inscripcion(id_atleta);
CREATE INDEX idx_inscripcion_competicion ON inscripcion(id_competicion);
CREATE INDEX idx_puntuacion_inscripcion  ON puntuacion(id_inscripcion);
