-- consultas.sql
-- Definición de Vistas para el Sistema de Gestión de Competiciones
-- Ejecutar después de crear las tablas (01_create_tables.sql)

SET NAMES utf8mb4;

USE gestion_competiciones;

-- ====================================================================
-- VISTAS
-- ====================================================================

-- Vista: Inscripciones detalladas con información de atleta y competición
-- Útil para ver el estado general de participación
DROP VIEW IF EXISTS vw_inscripciones_detalle;
CREATE OR REPLACE VIEW vw_inscripciones_detalle AS
SELECT
    i.id_inscripcion,
    CONCAT(a.nombre, ' ', a.apellido) AS atleta,
    c.nombre_evento AS competicion,
    c.fecha AS fecha_competicion,
    cat.nombre AS categoria,
    i.numero_dorsal,
    i.peso_registro,
    i.estatura_registro,
    i.fecha_inscripcion
FROM inscripcion i
JOIN atleta      a   ON i.id_atleta      = a.id_atleta
JOIN competicion c   ON i.id_competicion = c.id_competicion
LEFT JOIN categoria cat ON i.id_categoria = cat.id_categoria;

-- Vista: Ranking por competición
-- Muestra el orden final basado en la posición
DROP VIEW IF EXISTS vw_ranking_competicion;
CREATE OR REPLACE VIEW vw_ranking_competicion AS
SELECT
    c.id_competicion,
    c.nombre_evento,
    c.fecha,
    rf.ranking_final AS posicion_final,
    CONCAT(a.nombre, ' ', a.apellido) AS atleta,
    cat.nombre AS categoria,
    rf.media_ranking AS puntuacion_total,
    i.numero_dorsal
FROM competicion   c
JOIN resultado_final rf ON c.id_competicion = rf.id_competicion
JOIN inscripcion   i   ON i.id_inscripcion  = rf.id_inscripcion
JOIN atleta        a   ON a.id_atleta       = i.id_atleta
LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
WHERE rf.ranking_final IS NOT NULL
ORDER BY c.id_competicion, rf.ranking_final;

-- Vista: Puntuaciones por juez
-- Estadísticas de las evaluaciones realizadas por cada juez
DROP VIEW IF EXISTS vw_puntuaciones_juez;
CREATE OR REPLACE VIEW vw_puntuaciones_juez AS
SELECT
    j.id_juez,
    j.nombre AS juez,
    j.licencia,
    COUNT(p.id_inscripcion) AS total_evaluaciones,
    ROUND(AVG(p.ranking_otorgado), 2) AS ranking_promedio,
    MAX(p.ranking_otorgado) AS max_ranking,
    MIN(p.ranking_otorgado) AS min_ranking
FROM juez j
LEFT JOIN puntuacion p ON j.id_juez = p.id_juez
GROUP BY j.id_juez, j.nombre, j.licencia
ORDER BY total_evaluaciones DESC;
