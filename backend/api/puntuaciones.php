<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/roles.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;

    case 'POST':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_JUEZ);
        handlePost($pdo);
        break;

    case 'PUT':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN, ROLE_JUEZ);
        handlePut($pdo);
        break;

    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleDelete($pdo);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}

function handleGet(PDO $pdo): void
{
    $idInscripcion = filter_input(INPUT_GET, 'id_inscripcion', FILTER_VALIDATE_INT);

    if ($idInscripcion) {
        $stmt = $pdo->prepare(
            'SELECT p.id_puntuacion, p.id_inscripcion, p.id_juez,
                    j.nombre AS nombre_juez, j.licencia,
                    p.ranking_otorgado
               FROM puntuacion p
               JOIN juez j ON j.id_juez = p.id_juez
              WHERE p.id_inscripcion = ?
              ORDER BY j.licencia'
        );
        $stmt->execute([$idInscripcion]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    $idCompeticion = filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT);

    if ($idCompeticion) {
        $stmt = $pdo->prepare(
            'SELECT p.id_puntuacion,
                    CONCAT(a.nombre, " ", a.apellido) AS atleta,
                    j.nombre AS juez, j.licencia,
                    p.ranking_otorgado,
                    c.nombre_evento
               FROM puntuacion p
               JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
               JOIN atleta      a ON a.id_atleta      = i.id_atleta
               JOIN juez        j ON j.id_juez        = p.id_juez
               JOIN competicion c ON c.id_competicion = i.id_competicion
              WHERE i.id_competicion = ?
              ORDER BY a.apellido, j.licencia'
        );
        $stmt->execute([$idCompeticion]);
        echo json_encode($stmt->fetchAll());
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Se requiere id_inscripcion o id_competicion']);
}

function handlePost(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['id_inscripcion', 'id_juez', 'ranking_otorgado'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO puntuacion (id_inscripcion, id_juez, ranking_otorgado)
         VALUES (:id_inscripcion, :id_juez, :ranking_otorgado)
         ON DUPLICATE KEY UPDATE ranking_otorgado = VALUES(ranking_otorgado)'
    );
    $stmt->execute([
        ':id_inscripcion'   => (int) $data['id_inscripcion'],
        ':id_juez'          => (int) $data['id_juez'],
        ':ranking_otorgado' => (int) $data['ranking_otorgado'],
    ]);

    http_response_code(201);
    echo json_encode(['mensaje' => 'Puntuación registrada']);
}

function handlePut(PDO $pdo): void
{
    $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$id || !isset($data['ranking_otorgado'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID y ranking_otorgado son requeridos']);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE puntuacion SET ranking_otorgado = :ranking WHERE id_puntuacion = :id'
    );
    $stmt->execute([':ranking' => (int) $data['ranking_otorgado'], ':id' => $id]);

    echo json_encode(['mensaje' => 'Puntuación actualizada', 'filas' => $stmt->rowCount()]);
}

function handleDelete(PDO $pdo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM puntuacion WHERE id_puntuacion = ?');
    $stmt->execute([$id]);

    echo json_encode(['mensaje' => 'Puntuación eliminada', 'filas' => $stmt->rowCount()]);
}
