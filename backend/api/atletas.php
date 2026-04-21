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
        $stmt = $pdo->prepare('SELECT * FROM atleta WHERE id_atleta = ?');
        $stmt->execute([$id]);
        $atleta = $stmt->fetch();

        if (!$atleta) {
            http_response_code(404);
            echo json_encode(['error' => 'Atleta no encontrado']);
            return;
        }

        echo json_encode($atleta);
        return;
    }

    $stmt = $pdo->query(
        'SELECT id_atleta, nombre, apellido, fecha_nacimiento, nacionalidad
           FROM atleta
          ORDER BY apellido, nombre'
    );
    echo json_encode($stmt->fetchAll());
}

function handlePost(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['nombre', 'apellido', 'fecha_nacimiento'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            return;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO atleta (nombre, apellido, fecha_nacimiento, nacionalidad)
         VALUES (:nombre, :apellido, :fecha_nacimiento, :nacionalidad)'
    );
    $stmt->execute([
        ':nombre'           => trim($data['nombre']),
        ':apellido'         => trim($data['apellido']),
        ':fecha_nacimiento' => $data['fecha_nacimiento'],
        ':nacionalidad'     => strtoupper(trim($data['nacionalidad'] ?? '')),
    ]);

    http_response_code(201);
    echo json_encode(['id_atleta' => (int) $pdo->lastInsertId(), 'mensaje' => 'Atleta creado']);
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
        'UPDATE atleta
            SET nombre           = COALESCE(:nombre,           nombre),
                apellido         = COALESCE(:apellido,         apellido),
                fecha_nacimiento = COALESCE(:fecha_nacimiento, fecha_nacimiento),
                nacionalidad     = COALESCE(:nacionalidad,     nacionalidad)
          WHERE id_atleta = :id'
    );
    $stmt->execute([
        ':nombre'           => $data['nombre']           ?? null,
        ':apellido'         => $data['apellido']         ?? null,
        ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
        ':nacionalidad'     => isset($data['nacionalidad']) ? strtoupper(trim($data['nacionalidad'])) : null,
        ':id'               => $id,
    ]);

    echo json_encode(['mensaje' => 'Atleta actualizado', 'filas' => $stmt->rowCount()]);
}

function handleDelete(PDO $pdo): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM atleta WHERE id_atleta = ?');
    $stmt->execute([$id]);

    echo json_encode(['mensaje' => 'Atleta eliminado', 'filas' => $stmt->rowCount()]);
}
