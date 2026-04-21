-- ============================================================
-- ARCHIVO 4: insert_data.sql
-- Orden de ejecución: 4° (después de functions.sql)
-- Base de datos: MySQL
-- ============================================================

USE gestion_competiciones;

-- -------------------------------------------------------
-- Categorías
-- -------------------------------------------------------
INSERT IGNORE INTO categoria (nombre, altura_min, altura_max, peso_maximo_permitido)
VALUES
  ('Cadete',  1.40, 1.59,  60.00),
  ('Juvenil', 1.60, 1.75,  75.00),
  ('Senior',  1.76, 2.20, 120.00);

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
INSERT IGNORE INTO juez (nombre, licencia)
VALUES
  ('Roberto Diaz',  'JUE-001'),
  ('Ana Martinez',  'JUE-002'),
  ('Pedro Sanchez', 'JUE-003');

-- -------------------------------------------------------
-- Inscripciones + atletas
-- (inscribir_atleta crea el atleta si no existe)
--
-- Carlos Gomez     2005 → Cadete 2024, Juvenil 2025
-- Maria Rodriguez  2004 → Juvenil todos los eventos
-- Lucas Fernandez  2002 → Senior todos los eventos
-- Sofia Lopez      2003 → Juvenil desde 2024
-- Diego Herrera    2001 → Senior desde 2024
-- -------------------------------------------------------

-- === TORNEO APERTURA 2024 ===
CALL inscribir_atleta('Carlos',  'Gomez',      '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Cadete'),
  101, 55.50, 1.55);

CALL inscribir_atleta('Maria',   'Rodriguez',  '2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  102, 62.00, 1.68);

CALL inscribir_atleta('Lucas',   'Fernandez',  '2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  103, 85.00, 1.80);

CALL inscribir_atleta('Sofia',   'Lopez',      '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  104, 58.00, 1.65);

CALL inscribir_atleta('Diego',   'Herrera',    '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  105, 90.00, 1.82);

-- Puntuaciones: Torneo Apertura 2024
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 4
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 1
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Torneo Apertura 2024';

-- === COPA CIUDAD DE MADRID 2024 ===
CALL inscribir_atleta('Carlos',  'Gomez',      '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Cadete'),
  201, 56.00, 1.55);

CALL inscribir_atleta('Maria',   'Rodriguez',  '2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  202, 62.50, 1.68);

CALL inscribir_atleta('Lucas',   'Fernandez',  '2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  203, 86.00, 1.80);

CALL inscribir_atleta('Diego',   'Herrera',    '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2024'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  204, 90.50, 1.82);

-- Puntuaciones: Copa Ciudad de Madrid 2024
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 1
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Copa Ciudad de Madrid 2024';

-- === TORNEO APERTURA 2025 (Carlos sube de Cadete a Juvenil) ===
CALL inscribir_atleta('Carlos',  'Gomez',      '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  101, 63.00, 1.62);

CALL inscribir_atleta('Maria',   'Rodriguez',  '2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  102, 60.50, 1.68);

CALL inscribir_atleta('Lucas',   'Fernandez',  '2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  103, 87.00, 1.80);

CALL inscribir_atleta('Sofia',   'Lopez',      '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  104, 58.50, 1.65);

CALL inscribir_atleta('Diego',   'Herrera',    '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Torneo Apertura 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  105, 91.00, 1.82);

-- Puntuaciones: Torneo Apertura 2025
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 4
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 4
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 2
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Torneo Apertura 2025';

-- === COPA CIUDAD DE MADRID 2025 ===
CALL inscribir_atleta('Carlos',  'Gomez',      '2005-04-12', 'ESP',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  201, 63.50, 1.62);

CALL inscribir_atleta('Maria',   'Rodriguez',  '2004-08-22', 'MEX',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  202, 61.00, 1.68);

CALL inscribir_atleta('Lucas',   'Fernandez',  '2002-01-15', 'ARG',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  203, 87.50, 1.80);

CALL inscribir_atleta('Sofia',   'Lopez',      '2003-11-30', 'CHL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Juvenil'),
  204, 59.00, 1.65);

CALL inscribir_atleta('Diego',   'Herrera',    '2001-06-05', 'COL',
  (SELECT id_competicion FROM competicion WHERE nombre_evento = 'Copa Ciudad de Madrid 2025'),
  (SELECT id_categoria   FROM categoria   WHERE nombre = 'Senior'),
  205, 91.50, 1.82);

-- Puntuaciones: Copa Ciudad de Madrid 2025
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez,
  CASE
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Gomez'     AND j.licencia = 'JUE-003' THEN 1
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-001' THEN 3
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-002' THEN 2
    WHEN a.apellido = 'Rodriguez' AND j.licencia = 'JUE-003' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-001' THEN 2
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-002' THEN 3
    WHEN a.apellido = 'Fernandez' AND j.licencia = 'JUE-003' THEN 2
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-001' THEN 4
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-002' THEN 4
    WHEN a.apellido = 'Lopez'     AND j.licencia = 'JUE-003' THEN 5
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-001' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-002' THEN 1
    WHEN a.apellido = 'Herrera'   AND j.licencia = 'JUE-003' THEN 1
  END
FROM inscripcion i
JOIN atleta      a ON a.id_atleta      = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez        j ON j.licencia IN ('JUE-001','JUE-002','JUE-003')
WHERE c.nombre_evento = 'Copa Ciudad de Madrid 2025';

-- -------------------------------------------------------
-- Verificación
-- -------------------------------------------------------
SELECT 'atletas'     AS tabla, COUNT(*) AS total FROM atleta
UNION ALL
SELECT 'competicion',          COUNT(*)           FROM competicion
UNION ALL
SELECT 'inscripcion',          COUNT(*)           FROM inscripcion
UNION ALL
SELECT 'puntuacion',           COUNT(*)           FROM puntuacion;
