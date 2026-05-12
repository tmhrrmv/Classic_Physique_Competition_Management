<?php
declare(strict_types=1);

// ============================================================
// api/inscripciones.php — Listado de inscripciones con atributos
// ============================================================
// v1.0 - GET con join completo: atleta + competicion + categoria +
//        peso, estatura, dorsal, fecha_inscripcion
//      - Filtros: ?id_competicion=X, ?buscar=texto (nombre/apellido)
//      - Paginación estándar
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();

switch ($method) {
    case 'GET':
        header('Cache-Control: no-store, max-age=0');
        $payload = requireJwtAuth();
        handleInscripcionesGet($pdo);
        break;
    default:
        methodNotAllowed(['GET']);
}

// -------------------------------------------------------
// GET /api/inscripciones.php
// Parámetros opcionales:
//   ?id_competicion=X  → filtra por competición
//   ?buscar=texto      → filtra por nombre o apellido del atleta
//   ?page=N&limit=M    → paginación
// -------------------------------------------------------
function handleInscripcionesGet(PDO $pdo): void
{
    $p             = getPaginationParams(20, 100);
    $id_competicion = validateIntPositive(filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT));
    $buscar         = trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    $where  = [];
    $params = [];

    if ($id_competicion) {
        $where[]  = 'i.id_competicion = ?';
        $params[] = $id_competicion;
    }

    if ($buscar !== '') {
        $where[]  = '(a.nombre LIKE ? OR a.apellido LIKE ?)';
        $like     = '%' . $buscar . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT SQL_CALC_FOUND_ROWS
                i.id_inscripcion,
                i.numero_dorsal,
                i.peso_registro,
                i.estatura_registro,
                i.fecha_inscripcion,
                a.id_atleta,
                a.nombre,
                a.apellido,
                c.id_competicion,
                c.nombre_evento,
                cat.nombre AS categoria
           FROM inscripcion i
           JOIN atleta      a   ON a.id_atleta      = i.id_atleta
           JOIN competicion c   ON c.id_competicion = i.id_competicion
           LEFT JOIN categoria cat ON cat.id_categoria = i.id_categoria
         {$whereClause}
          ORDER BY c.fecha DESC, i.numero_dorsal ASC
          LIMIT ? OFFSET ?"
    );

    $params[] = $p['limit'];
    $params[] = $p['offset'];
    $stmt->execute($params);

    paginatedResponse($pdo, $stmt->fetchAll(), $p);
}
