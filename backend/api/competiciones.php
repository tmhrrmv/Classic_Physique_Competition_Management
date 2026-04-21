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
        requireRole($payload, ROLE_ADMIN);
        handlePost($pdo);
        break;

    case 'PUT':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
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
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM competicion WHERE id_competicion = ?');
        $stmt->execute([$id]);
        $competicion = $stmt->fetch();

        if (!$competicion) {
            http_response_code(404);
            echo json_encode(['error' => 'Competición no encontrada']);
            return;
        }

        echo json_encode($competicion);
        return;
    }

    $stmt = $pdo->query(
        'SELECT id_competicion, nombre_evento, fecha, lugar
           FROM competicion
          ORDER BY fecha DESC'
    );
    echo json_encode($stmt->fetchAll());
}

function handlePost(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['nombre_evento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'nombre_evento es requerido']);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO competicion (nombre_evento, fecha, lugar)
         VALUES (:nombre_evento, :fecha, :lugar)'
    );
    $stmt->execute([
        ':nombre_evento' => trim($data['nombre_evento']),
        ':fecha'         => $data['fecha']  ?? null,
        ':lugar'         => $data['lugar']  ?? null,
    ]);

    http_response_code(201);
    echo json_encode(['id_competicion' => (int) $pdo->lastInsertId(), 'mensaje' => 'Competición creada']);
}

function handlePut(PDO $pdo): void
{
    $id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE competicion
            SET nombre_evento = COALESCE(:nombre_evento, nombre_evento),
                fecha         = COALESCE(:fecha,         fecha),
                lugar         = COALESCE(:lugar,         lugar)
          WHERE id_competicion = :id'
    );
    $stmt->execute([
        ':nombre_evento' => $data['nombre_evento'] ?? null,
        ':fecha'         => $data['fecha']         ?? null,
        ':lugar'         => $data['lugar']         ?? null,
        ':id'            => $id,
    ]);

    echo json_encode(['mensaje' => 'Competición actualizada', 'filas' => $stmt->rowCount()]);
}

function handleDelete(PDO $pdo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM competicion WHERE id_competicion = ?');
    $stmt->execute([$id]);

    echo json_encode(['mensaje' => 'Competición eliminada', 'filas' => $stmt->rowCount()]);
}
