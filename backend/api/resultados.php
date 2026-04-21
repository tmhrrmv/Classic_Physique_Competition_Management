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
        handleCalcular($pdo);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}

function handleGet(PDO $pdo): void
{
    $idCompeticion = filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT);

    if (!$idCompeticion) {
        http_response_code(400);
        echo json_encode(['error' => 'id_competicion es requerido']);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT cat.nombre AS categoria,
                rf.ranking_final AS puesto,
                CONCAT(a.nombre, " ", a.apellido) AS atleta,
                a.nacionalidad,
                rf.media_ranking,
                rf.num_jueces,
                rf.fecha_calculo
           FROM resultado_final rf
           JOIN inscripcion i   ON i.id_inscripcion = rf.id_inscripcion
           JOIN atleta      a   ON a.id_atleta      = i.id_atleta
           LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
          WHERE rf.id_competicion = ?
          ORDER BY cat.nombre, rf.ranking_final'
    );
    $stmt->execute([$idCompeticion]);
    $resultados = $stmt->fetchAll();

    if (empty($resultados)) {
        http_response_code(404);
        echo json_encode(['error' => 'Sin resultados para esta competición. ¿Ya se calcularon?']);
        return;
    }

    echo json_encode($resultados);
}

function handleCalcular(PDO $pdo): void
{
    $data          = json_decode(file_get_contents('php://input'), true);
    $idCompeticion = filter_var($data['id_competicion'] ?? null, FILTER_VALIDATE_INT);

    if (!$idCompeticion) {
        http_response_code(400);
        echo json_encode(['error' => 'id_competicion es requerido']);
        return;
    }

    $stmt = $pdo->prepare('CALL calcular_resultados_competicion(:id)');
    $stmt->execute([':id' => $idCompeticion]);
    $resultados = $stmt->fetchAll();

    echo json_encode([
        'mensaje'    => 'Resultados calculados correctamente',
        'resultados' => $resultados,
    ]);
}
