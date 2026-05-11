USE railway;

-- ============================================================
-- COMPETICIONES
-- Fechas pasadas = cerradas, futuras = abiertas
-- fn_estado_competicion calcula el estado por fecha
-- ============================================================
INSERT IGNORE INTO competicion (nombre_evento, fecha, lugar) VALUES
  ('Torneo Apertura 2024',        '2024-03-10', 'Pabellón Municipal, Madrid'),
  ('Copa Ciudad de Madrid 2024',  '2024-06-15', 'Palacio de Deportes, Madrid'),
  ('Torneo Apertura 2025',        '2025-03-09', 'Pabellón Municipal, Madrid'),
  ('Copa Ciudad de Madrid 2025',  '2025-06-22', 'Palacio de Deportes, Madrid'),
  ('Campeonato Nacional 2026',    '2026-07-20', 'WiZink Center, Madrid'),
  ('Copa Verano 2026',            '2026-09-14', 'Palacio de los Deportes, Sevilla');

-- ============================================================
-- ATLETAS
-- Cadete:  nacido entre 2007-2010 (edad 14-17 en 2024)
-- Juvenil: nacido entre 2001-2006 (edad 18-23 en 2024)
-- Senior:  nacido entre 1990-2000 (edad 24+ en 2024)
-- nacionalidad: código ISO 3 letras mayúsculas
-- ============================================================
INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad, activo) VALUES
  -- Juveniles
  ('Carlos',   'Gomez',      '2002-04-12', 'ESP', 1),
  ('Maria',    'Rodriguez',  '2003-08-22', 'ESP', 1),
  ('Lucas',    'Fernandez',  '2001-01-15', 'ARG', 1),
  -- Seniors
  ('Diego',    'Herrera',    '1998-06-05', 'COL', 1),
  ('Sofia',    'Lopez',      '1997-11-30', 'ESP', 1),
  ('Andres',   'Perez',      '1995-03-18', 'MEX', 1),
  -- Cadetes
  ('Pablo',    'Martinez',   '2008-07-25', 'ESP', 1),
  ('Laura',    'Garcia',     '2009-02-14', 'ESP', 1);

-- ============================================================
-- INSCRIPCIONES
-- Reglas:
--   - Solo atletas activos
--   - Solo competiciones no cerradas para nuevas inscripciones
--     PERO podemos insertar directamente en la tabla para datos históricos
--   - peso y estatura deben respetar los límites de la categoría:
--     Cadete:  altura 1.40-1.59m, peso max 60kg,  edad 14-17
--     Juvenil: altura 1.60-1.75m, peso max 75kg,  edad 18-23
--     Senior:  altura 1.76-2.20m, peso max 120kg, edad 24+
--   - dorsal único por competición
-- ============================================================

-- Torneo Apertura 2024 (cerrada)
INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 101, 68.50, 1.72
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Carlos' AND a.apellido='Gomez'
  AND c.nombre_evento='Torneo Apertura 2024'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 102, 65.00, 1.68
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Maria' AND a.apellido='Rodriguez'
  AND c.nombre_evento='Torneo Apertura 2024'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 103, 88.00, 1.80
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Diego' AND a.apellido='Herrera'
  AND c.nombre_evento='Torneo Apertura 2024'
  AND cat.nombre='Senior';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 104, 92.00, 1.82
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Sofia' AND a.apellido='Lopez'
  AND c.nombre_evento='Torneo Apertura 2024'
  AND cat.nombre='Senior';

-- Copa Ciudad de Madrid 2024 (cerrada)
INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 201, 69.00, 1.72
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Carlos' AND a.apellido='Gomez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2024'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 202, 70.00, 1.74
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Lucas' AND a.apellido='Fernandez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2024'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 203, 95.00, 1.85
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Andres' AND a.apellido='Perez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2024'
  AND cat.nombre='Senior';

-- Torneo Apertura 2025 (cerrada)
INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 101, 55.00, 1.55
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Pablo' AND a.apellido='Martinez'
  AND c.nombre_evento='Torneo Apertura 2025'
  AND cat.nombre='Cadete';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 102, 52.00, 1.50
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Laura' AND a.apellido='Garcia'
  AND c.nombre_evento='Torneo Apertura 2025'
  AND cat.nombre='Cadete';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 103, 71.00, 1.73
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Carlos' AND a.apellido='Gomez'
  AND c.nombre_evento='Torneo Apertura 2025'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 104, 90.00, 1.81
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Diego' AND a.apellido='Herrera'
  AND c.nombre_evento='Torneo Apertura 2025'
  AND cat.nombre='Senior';

-- Copa Ciudad de Madrid 2025 (cerrada)
INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 301, 72.00, 1.73
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Carlos' AND a.apellido='Gomez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2025'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 302, 66.00, 1.69
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Maria' AND a.apellido='Rodriguez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2025'
  AND cat.nombre='Juvenil';

INSERT IGNORE INTO inscripcion (id_atleta, id_competicion, id_categoria, numero_dorsal, peso_registro, estatura_registro)
SELECT a.id_atleta, c.id_competicion, cat.id_categoria, 303, 93.00, 1.83
FROM atleta a, competicion c, categoria cat
WHERE a.nombre='Andres' AND a.apellido='Perez'
  AND c.nombre_evento='Copa Ciudad de Madrid 2025'
  AND cat.nombre='Senior';

-- ============================================================
-- PUNTUACIONES
-- Los 3 jueces puntúan a cada atleta inscrito en competiciones cerradas
-- ranking_otorgado >= 1, único por inscripcion+juez
-- Con 3 jueces se aplica descarte de extremos en sp_calcular_resultados
-- ============================================================

-- Torneo Apertura 2024 — 4 atletas, 3 jueces = 12 puntuaciones

-- Carlos Gomez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Roberto Diaz'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Ana Martinez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Pedro Sanchez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2024';

-- Maria Rodriguez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Roberto Diaz'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Ana Martinez'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Pedro Sanchez'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Torneo Apertura 2024';

-- Diego Herrera (Senior)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Roberto Diaz'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Ana Martinez'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Pedro Sanchez'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2024';

-- Sofia Lopez (Senior)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Roberto Diaz'
WHERE a.nombre='Sofia' AND a.apellido='Lopez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Ana Martinez'
WHERE a.nombre='Sofia' AND a.apellido='Lopez' AND c.nombre_evento='Torneo Apertura 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i
JOIN atleta a ON a.id_atleta = i.id_atleta
JOIN competicion c ON c.id_competicion = i.id_competicion
JOIN juez j ON j.nombre = 'Pedro Sanchez'
WHERE a.nombre='Sofia' AND a.apellido='Lopez' AND c.nombre_evento='Torneo Apertura 2024';

-- Copa Ciudad de Madrid 2024 — 3 atletas, 3 jueces = 9 puntuaciones

-- Carlos Gomez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

-- Lucas Fernandez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Lucas' AND a.apellido='Fernandez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Lucas' AND a.apellido='Fernandez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Lucas' AND a.apellido='Fernandez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

-- Andres Perez (Senior)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2024';

-- Torneo Apertura 2025 — 4 atletas, 3 jueces = 12 puntuaciones

-- Pablo Martinez (Cadete)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Pablo' AND a.apellido='Martinez' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Pablo' AND a.apellido='Martinez' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Pablo' AND a.apellido='Martinez' AND c.nombre_evento='Torneo Apertura 2025';

-- Laura Garcia (Cadete)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Laura' AND a.apellido='Garcia' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Laura' AND a.apellido='Garcia' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Laura' AND a.apellido='Garcia' AND c.nombre_evento='Torneo Apertura 2025';

-- Carlos Gomez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Torneo Apertura 2025';

-- Diego Herrera (Senior)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Diego' AND a.apellido='Herrera' AND c.nombre_evento='Torneo Apertura 2025';

-- Copa Ciudad de Madrid 2025 — 3 atletas, 3 jueces = 9 puntuaciones

-- Carlos Gomez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Carlos' AND a.apellido='Gomez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

-- Maria Rodriguez (Juvenil)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 2
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Maria' AND a.apellido='Rodriguez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

-- Andres Perez (Senior)
INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Roberto Diaz'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Ana Martinez'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

INSERT IGNORE INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
SELECT i.id_inscripcion, j.id_juez, 1
FROM inscripcion i JOIN atleta a ON a.id_atleta=i.id_atleta
JOIN competicion c ON c.id_competicion=i.id_competicion
JOIN juez j ON j.nombre='Pedro Sanchez'
WHERE a.nombre='Andres' AND a.apellido='Perez' AND c.nombre_evento='Copa Ciudad de Madrid 2025';

-- ============================================================
-- VERIFICACIÓN FINAL
-- ============================================================
SELECT 'competicion'    AS tabla, COUNT(*) AS total FROM competicion
UNION ALL SELECT 'atleta',        COUNT(*) FROM atleta
UNION ALL SELECT 'inscripcion',   COUNT(*) FROM inscripcion
UNION ALL SELECT 'puntuacion',    COUNT(*) FROM puntuacion
UNION ALL SELECT 'resultado_final', COUNT(*) FROM resultado_final;
