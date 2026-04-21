-- ============================================================
-- schema_completo_v5.sql  (MySQL 8.0+)
-- ============================================================
-- CAMBIOS RESPECTO A v4:
--
--  [E6] calcular_resultados_competicion usaba AVG simple.
--       El reglamento descarta el ranking más alto (peor posición)
--       y el más bajo (mejor posición) antes de calcular la media.
--       Solución: en tmp_medias se excluyen MAX y MIN mediante
--       (SUM - MAX - MIN) / (COUNT - 2). Si hay menos de 3 jueces
--       no se puede descartar, se usa AVG normal como fallback.
--
--  [E7] Copa Ciudad de Madrid 2024 y 2025 no tenían puntuaciones.
--       Solución: añadidas puntuaciones de prueba para todos
--       los eventos, con rankings distintos por categoría.
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
  id_atleta        INT          NOT NULL AUTO_INCREMENT,
  nombre           VARCHAR(100) NOT NULL,
  apellido         VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE         NOT NULL,
  nacionalidad     VARCHAR(3)   DEFAULT NULL,
  PRIMARY KEY (id_atleta),
  UNIQUE KEY uq_atleta (nombre, apellido, fecha_nacimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE juez (
  id_juez  INT          NOT NULL AUTO_INCREMENT,
  nombre   VARCHAR(200) NOT NULL,
  licencia VARCHAR(50)  NOT NULL,
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
  UNIQUE KEY uq_atleta_evento  (id_atleta, id_competicion),
  UNIQUE KEY uq_dorsal_evento  (id_competicion, numero_dorsal),
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

-- resultado_final: una fila por inscripción, se recalcula con UPSERT
CREATE TABLE resultado_final (
  id_resultado   INT           NOT NULL AUTO_INCREMENT,
  id_inscripcion INT           NOT NULL,
  id_competicion INT           NOT NULL,
  id_categoria   INT           DEFAULT NULL,
  ranking_final  INT           DEFAULT NULL,
  media_ranking  DECIMAL(5,2)  DEFAULT NULL,
  num_jueces     INT           DEFAULT NULL,
  fecha_calculo  DATETIME      NOT NULL
                               DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_resultado),
  UNIQUE KEY uq_resultado_inscripcion (id_inscripcion),
  CONSTRAINT chk_ranking_final_positivo
    CHECK (ranking_final IS NULL OR ranking_final >= 1),
  CONSTRAINT fk_res_inscripcion
    FOREIGN KEY (id_inscripcion) REFERENCES inscripcion(id_inscripcion) ON DELETE CASCADE,
  CONSTRAINT fk_res_competicion
    FOREIGN KEY (id_competicion) REFERENCES competicion(id_competicion) ON DELETE CASCADE,
  CONSTRAINT fk_res_categoria
    FOREIGN KEY (id_categoria)   REFERENCES categoria(id_categoria)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE INDEX idx_inscripcion_atleta      ON inscripcion(id_atleta);
CREATE INDEX idx_inscripcion_competicion ON inscripcion(id_competicion);
CREATE INDEX idx_puntuacion_inscripcion  ON puntuacion(id_inscripcion);
CREATE INDEX idx_resultado_competicion   ON resultado_final(id_competicion);
CREATE INDEX idx_resultado_cat_comp      ON resultado_final(id_competicion, id_categoria);


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
-- PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS inscribir_atleta;
DROP PROCEDURE IF EXISTS actualizar_datos_inscripcion;
DROP PROCEDURE IF EXISTS historial_atleta;
DROP PROCEDURE IF EXISTS atletas_sin_inscripcion_en_evento;
DROP PROCEDURE IF EXISTS calcular_resultados_competicion;

DELIMITER $$

CREATE PROCEDURE inscribir_atleta(
  IN p_nombre            VARCHAR(100),
  IN p_apellido          VARCHAR(100),
  IN p_fecha_nacimiento  DATE,
  IN p_nacionalidad      VARCHAR(3),
  IN p_id_competicion    INT,
  IN p_id_categoria      INT,
  IN p_numero_dorsal     INT,
  IN p_peso_registro     DECIMAL(6,2),
  IN p_estatura_registro DECIMAL(5,2)
)
BEGIN
  DECLARE v_id_atleta      INT DEFAULT NULL;
  DECLARE v_id_inscripcion INT DEFAULT NULL;
  DECLARE v_ya_inscrito    INT DEFAULT 0;

  IF p_id_competicion IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debes indicar el evento (p_id_competicion) al inscribir un atleta';
  END IF;

  INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
  VALUES (p_nombre, p_apellido, p_fecha_nacimiento, p_nacionalidad);

  SELECT id_atleta INTO v_id_atleta
    FROM atleta
   WHERE nombre           = p_nombre
     AND apellido         = p_apellido
     AND fecha_nacimiento = p_fecha_nacimiento
   LIMIT 1;

  SELECT COUNT(*) INTO v_ya_inscrito
    FROM inscripcion
   WHERE id_atleta      = v_id_atleta
     AND id_competicion = p_id_competicion;

  IF v_ya_inscrito > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El atleta ya está inscrito en este evento. Usa actualizar_datos_inscripcion() para corregir sus datos.';
  END IF;

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


CREATE PROCEDURE actualizar_datos_inscripcion(
  IN p_id_inscripcion    INT,
  IN p_id_categoria      INT,
  IN p_numero_dorsal     INT,
  IN p_limpiar_dorsal    TINYINT(1),
  IN p_peso_registro     DECIMAL(6,2),
  IN p_estatura_registro DECIMAL(5,2)
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM inscripcion WHERE id_inscripcion = p_id_inscripcion) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No existe una inscripción con ese id';
  END IF;

  UPDATE inscripcion SET
    id_categoria      = COALESCE(p_id_categoria,      id_categoria),
    numero_dorsal     = CASE
                          WHEN p_limpiar_dorsal = 1 THEN NULL
                          ELSE COALESCE(p_numero_dorsal, numero_dorsal)
                        END,
    peso_registro     = COALESCE(p_peso_registro,     peso_registro),
    estatura_registro = COALESCE(p_estatura_registro, estatura_registro)
  WHERE id_inscripcion = p_id_inscripcion;

  SELECT 'Inscripción actualizada correctamente' AS mensaje,
         ROW_COUNT() AS filas_afectadas;
END$$


CREATE PROCEDURE historial_atleta(
  IN p_id_atleta INT
)
BEGIN
  SELECT
    a.nombre                   AS nombre_atleta,
    a.apellido                 AS apellido_atleta,
    c.nombre_evento,
    c.fecha                    AS fecha_evento,
    c.lugar,
    cat.nombre                 AS categoria,
    i.numero_dorsal,
    i.peso_registro            AS peso_en_ese_evento,
    i.estatura_registro        AS estatura_en_ese_evento,
    i.fecha_inscripcion,
    rf.ranking_final,
    rf.media_ranking,
    rf.num_jueces
  FROM inscripcion i
  JOIN atleta        a   ON a.id_atleta       = i.id_atleta
  JOIN competicion   c   ON c.id_competicion  = i.id_competicion
  LEFT JOIN categoria    cat ON cat.id_categoria  = i.id_categoria
  LEFT JOIN resultado_final rf ON rf.id_inscripcion = i.id_inscripcion
  WHERE i.id_atleta = p_id_atleta
  ORDER BY c.fecha;
END$$


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


-- -------------------------------------------------------
-- PROCEDURE: calcular_resultados_competicion
--
-- [E6] Implementa el reglamento real del documento:
--      si hay 3 o más jueces se descartan el ranking más
--      alto (peor posición) y el más bajo (mejor posición)
--      de cada atleta antes de calcular la media.
--      Con menos de 3 jueces se usa la media simple como
--      fallback (no se puede descartar con tan pocos datos).
--
--      Fórmula con descarte:
--        media_ajustada = (SUM - MAX - MIN) / (COUNT - 2)
--
-- El ranking final es por categoría: los Senior no compiten
-- contra los Juvenil ni los Cadete.
-- -------------------------------------------------------
CREATE PROCEDURE calcular_resultados_competicion(
  IN p_id_competicion INT
)
BEGIN

  -- Paso 1: calcular media ajustada por (inscripcion, categoria)
  -- [E6] Si n_jueces >= 3 se descarta MAX y MIN antes de promediar
  DROP TEMPORARY TABLE IF EXISTS tmp_medias;
  CREATE TEMPORARY TABLE tmp_medias AS
  SELECT
    i.id_inscripcion,
    i.id_categoria,
    COUNT(p.id_puntuacion)   AS n_jueces,
    CASE
      WHEN COUNT(p.id_puntuacion) >= 3
        THEN ROUND(
               (SUM(p.ranking_otorgado) - MAX(p.ranking_otorgado) - MIN(p.ranking_otorgado))
               / (COUNT(p.id_puntuacion) - 2),
             2)
      ELSE
        ROUND(AVG(p.ranking_otorgado), 2)
    END AS media
  FROM inscripcion i
  JOIN puntuacion  p ON p.id_inscripcion = i.id_inscripcion
  WHERE i.id_competicion   = p_id_competicion
    AND p.ranking_otorgado IS NOT NULL
  GROUP BY i.id_inscripcion, i.id_categoria;

  -- Paso 2: calcular RANK() por categoría
  DROP TEMPORARY TABLE IF EXISTS tmp_ranking;
  CREATE TEMPORARY TABLE tmp_ranking AS
  SELECT
    id_inscripcion,
    id_categoria,
    media,
    n_jueces,
    RANK() OVER (
      PARTITION BY id_categoria
      ORDER BY media ASC
    ) AS ranking_final
  FROM tmp_medias;

  -- Paso 3: UPSERT en resultado_final
  INSERT INTO resultado_final
    (id_inscripcion, id_competicion, id_categoria, ranking_final, media_ranking, num_jueces)
  SELECT
    r.id_inscripcion,
    p_id_competicion,
    r.id_categoria,
    r.ranking_final,
    r.media,
    r.n_jueces
  FROM tmp_ranking r
  ON DUPLICATE KEY UPDATE
    id_competicion = VALUES(id_competicion),
    id_categoria   = VALUES(id_categoria),
    ranking_final  = VALUES(ranking_final),
    media_ranking  = VALUES(media_ranking),
    num_jueces     = VALUES(num_jueces),
    fecha_calculo  = CURRENT_TIMESTAMP;

  DROP TEMPORARY TABLE IF EXISTS tmp_ranking;
  DROP TEMPORARY TABLE IF EXISTS tmp_medias;

  -- Devolver el podio completo agrupado por categoría
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

DELIMITER ;


-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

INSERT IGNORE INTO categoria (nombre, altura_min, altura_max, peso_maximo_permitido) VALUES
  ('Cadete',  1.40, 1.59,  60.00),
  ('Juvenil', 1.60, 1.75,  75.00),
  ('Senior',  1.76, 2.20, 120.00);

INSERT IGNORE INTO competicion (nombre_evento, fecha, lugar) VALUES
  ('Torneo Apertura 2024',       '2024-03-10', 'Estadio Nacional'),
  ('Copa Ciudad de Madrid 2024', '2024-09-21', 'Centro Deportivo Municipal'),
  ('Torneo Apertura 2025',       '2025-03-09', 'Estadio Nacional'),
  ('Copa Ciudad de Madrid 2025', '2025-09-20', 'Centro Deportivo Municipal');

-- 3 jueces para poder aplicar el descarte de extremos
INSERT IGNORE INTO juez (nombre, licencia) VALUES
  ('Roberto Diaz',  'JUE-001'),
  ('Ana Martinez',  'JUE-002'),
  ('Pedro Sanchez', 'JUE-003');

-- -------------------------------------------------------
-- Inscripciones
-- -------------------------------------------------------

-- === TORNEO APERTURA 2024 ===
CALL inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Cadete'),   101, 55.50, 1.55);

CALL inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  102, 62.00, 1.68);

CALL inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   103, 85.00, 1.80);

CALL inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   104, 90.00, 1.82);

-- === COPA CIUDAD DE MADRID 2024 ===
CALL inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Cadete'),   201, 56.00, 1.55);

CALL inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   202, 86.50, 1.80);

CALL inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   203, 90.50, 1.82);

-- === TORNEO APERTURA 2025 (Carlos sube Cadete → Juvenil) ===
CALL inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  101, 63.00, 1.62);

CALL inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  102, 60.50, 1.68);

CALL inscribir_atleta('Sofia',  'Lopez',    '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  103, 58.00, 1.65);

CALL inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   104, 87.00, 1.80);

CALL inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   105, 91.00, 1.82);

-- === COPA CIUDAD DE MADRID 2025 ===
CALL inscribir_atleta('Carlos', 'Gomez',    '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  201, 63.50, 1.62);

CALL inscribir_atleta('Sofia',  'Lopez',    '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  202, 58.50, 1.65);

CALL inscribir_atleta('Maria',  'Rodriguez','2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),  203, 61.00, 1.68);

CALL inscribir_atleta('Lucas',  'Fernandez','2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   204, 87.50, 1.80);

CALL inscribir_atleta('Diego',  'Herrera',  '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),   205, 91.50, 1.82);

-- -------------------------------------------------------
-- Puntuaciones (3 jueces en todos los eventos)
-- [E6] Con 3 jueces se descarta el MAX y el MIN → solo
--      queda la nota del juez central para la media.
-- [E7] Todos los eventos tienen puntuaciones ahora.
-- -------------------------------------------------------

-- === TORNEO APERTURA 2024 ===
-- Cadete: solo Carlos → 1º
-- Juvenil: solo Maria → 1ª
-- Senior: Lucas 1º, Diego 2º
INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     THEN 1
    WHEN a.apellido = 'Rodriguez' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 2
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Torneo Apertura 2024';

-- === COPA CIUDAD DE MADRID 2024 ===
-- Cadete: solo Carlos → 1º
-- Senior: Lucas 1º, Diego 2º (los 3 jueces coinciden)
INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     THEN 1
    WHEN a.apellido = 'Fernandez' THEN 1
    WHEN a.apellido = 'Herrera'   THEN 2
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Copa Ciudad de Madrid 2024';

-- === TORNEO APERTURA 2025 ===
-- Juvenil: Carlos 1º, Sofia 2ª, Maria 3ª (con algo de discrepancia entre jueces)
-- Senior:  Diego 1º, Lucas 2º (discrepancia entre jueces → descarte aplica)
INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 1
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Torneo Apertura 2025';

-- === COPA CIUDAD DE MADRID 2025 ===
-- Juvenil: Sofia 1ª, Carlos 2º, Maria 3ª
-- Senior:  Diego 1º, Lucas 2º
INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 2
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Copa Ciudad de Madrid 2025';


-- ============================================================
-- VERIFICACIONES (descomentar para probar)
-- ============================================================

-- Podio Torneo Apertura 2024:
-- CALL calcular_resultados_competicion(
--   (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'));

-- Podio Copa Ciudad de Madrid 2024:
-- CALL calcular_resultados_competicion(
--   (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'));

-- Podio Torneo Apertura 2025 (descarte de extremos activo con 3 jueces):
-- CALL calcular_resultados_competicion(
--   (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'));

-- Podio Copa Ciudad de Madrid 2025:
-- CALL calcular_resultados_competicion(
--   (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'));

-- Historial completo de Carlos (con ranking_final por evento):
-- CALL historial_atleta(
--   (SELECT id_atleta FROM atleta WHERE nombre = 'Carlos' AND apellido = 'Gomez'));

-- Atletas no inscritos en Torneo Apertura 2025:
-- CALL atletas_sin_inscripcion_en_evento(
--   (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'));

-- Borrar dorsal de una inscripción (p_limpiar_dorsal = 1):
-- CALL actualizar_datos_inscripcion(1, NULL, NULL, 1, NULL, NULL);

-- Vista general de todas las inscripciones:
-- SELECT a.nombre, a.apellido, c.nombre_evento, c.fecha,
--        cat.nombre AS categoria, i.peso_registro, i.estatura_registro
--   FROM inscripcion i
--   JOIN atleta      a   ON a.id_atleta      = i.id_atleta
--   JOIN competicion c   ON c.id_competicion = i.id_competicion
--   LEFT JOIN categoria cat ON cat.id_categoria = i.id_categoria
--  ORDER BY a.apellido, c.fecha;

-- Recuento de registros:
-- SELECT 'atletas' AS tabla, COUNT(*) AS total FROM atleta
-- UNION ALL SELECT 'competicion', COUNT(*) FROM competicion
-- UNION ALL SELECT 'inscripcion',  COUNT(*) FROM inscripcion
-- UNION ALL SELECT 'puntuacion',   COUNT(*) FROM puntuacion;
