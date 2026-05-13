<?php
declare(strict_types=1);

// ============================================================
// api/inscripciones.php — Gestión de inscripciones
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - Endpoint nuevo: GET lista paginada con JOINs
//        Filtros: ?id_competicion=X, ?buscar=texto
//        GET devuelve dorsal, atleta, competicion,
//        categoria, peso, estatura, fecha_inscripcion
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';

$method   = $_SERVER['REQUEST_METHOD'];
$pdo      = getConnection();
$raw_body = $method === 'POST' ? file_get_contents('php://input') : '';

switch ($method) {
    case 'GET':
        $payload = requireJwtAuth();
        handleInscGet($pdo);
        break;

    case 'DELETE':
        $payload = requireJwtAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_ORGANIZADOR);
        handleInscDelete($pdo);
        break;

    default:
        methodNotAllowed(['GET', 'DELETE']);
}

// -------------------------------------------------------
// GET — lista paginada de inscripciones con todos los datos
// Filtros: ?id_competicion=X, ?buscar=texto
// -------------------------------------------------------
function handleInscGet(PDO $pdo): void
{
    header('Cache-Control: no-store, max-age=0');

    $id_competicion = validateIntPositive(filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT));
    $buscar         = trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $p              = getPaginationParams(20, 100);

    // Base de la query con todos los campos necesarios para la tabla
    $sql = 'SELECT SQL_CALC_FOUND_ROWS
                   i.id_inscripcion,
                   i.numero_dorsal,
                   a.nombre,
                   a.apellido,
                   a.nacionalidad,
                   c.nombre_evento,
                   cat.nombre        AS categoria,
                   i.peso_registro,
                   i.estatura_registro,
                   i.fecha_inscripcion
              FROM inscripcion i
              JOIN atleta      a   ON a.id_atleta      = i.id_atleta
              JOIN competicion c   ON c.id_competicion = i.id_competicion
              LEFT JOIN categoria cat ON cat.id_categoria = i.id_categoria
             WHERE 1=1';

    $params = [];

    // Filtro por competicion
    if ($id_competicion) {
        $sql     .= ' AND i.id_competicion = ?';
        $params[] = $id_competicion;
    }

    // Filtro por nombre/apellido del atleta
    if ($buscar !== '') {
        $sql     .= ' AND (a.nombre LIKE ? OR a.apellido LIKE ? OR CONCAT(a.nombre, " ", a.apellido) LIKE ?)';
        $like     = '%' . $buscar . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY c.fecha DESC, a.apellido, a.nombre LIMIT ? OFFSET ?';
    $params[] = $p['limit'];
    $params[] = $p['offset'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    paginatedResponse($pdo, $stmt->fetchAll(), $p);
}

// -------------------------------------------------------
// DELETE — eliminar inscripcion
// Solo si la competicion no está cerrada
// -------------------------------------------------------
function handleInscDelete(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID de inscripcion requerido'], 400);
        return;
    }

    // Verificar que existe y obtener estado de la competicion
    $check = $pdo->prepare(
        'SELECT i.id_inscripcion, fn_estado_competicion(i.id_competicion) AS estado
           FROM inscripcion i WHERE i.id_inscripcion = ?'
    );
    $check->execute([$id]);
    $row = $check->fetch();

    if (!$row) {
        jsonResponse(['error' => 'Inscripcion no encontrada'], 404);
        return;
    }

    if ($row['estado'] === 'cerrada') {
        jsonResponse(['error' => 'No se puede eliminar una inscripcion de una competicion cerrada'], 409);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM inscripcion WHERE id_inscripcion = ?');
    $stmt->execute([$id]);

    jsonResponse(['mensaje' => 'Inscripcion eliminada correctamente']);
}
