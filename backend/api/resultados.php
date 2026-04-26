<?php
declare(strict_types=1);

// ============================================================
// api/resultados.php — Gestión de resultados y podio
// ============================================================
// HISTORIAL DE CAMBIOS
// v1.0 - GET resultados y POST calcular básico
// v1.1 - Migrado a sp_calcular_resultados
//      - Aplicadas todas las mejoras del checklist:
//        helpers.php, Content-Type, paginación, validaciones,
//        logError, jsonResponse, Allow, Cache-Control,
//        X-Total-Count, SQL_CALC_FOUND_ROWS
//      - GET devuelve podio agrupado por categoría
//      - GET ?id_competicion=X&categoria=X filtra por categoría
//      - GET ?id_atleta=X devuelve ranking histórico del atleta
//      - POST calcula resultados via sp_calcular_resultados
//        Solo funciona si la competición está en_curso o cerrada
//      - DELETE limpia resultados de una competición
//        Solo admin, y solo si la competición sigue abierta
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

    // GET /api/resultados?id_competicion=X          → podio completo
    // GET /api/resultados?id_competicion=X&categoria=X → filtrar categoría
    // GET /api/resultados?id_atleta=X               → historial de rankings
    // Accesible para todos los roles
    case 'GET':
        handleResGet($pdo);
        break;

    // POST /api/resultados → calcular podio via sp_calcular_resultados
    // Solo funciona si competición está en_curso o cerrada
    // Roles: admin
    case 'POST':
        validateContentType();
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleResPost($pdo, $payload, $raw_body);
        break;

    // DELETE /api/resultados?id_competicion=X → limpiar resultados
    // Solo si la competición está abierta (sin resultados definitivos)
    // Roles: admin
    case 'DELETE':
        $payload = requireAuth();
        requireRole($payload, ROLE_ADMIN);
        handleResDelete($pdo);
        break;

    default:
        methodNotAllowed(['GET','POST','DELETE']);
}

// -------------------------------------------------------
// GET
// -------------------------------------------------------
function handleResGet(PDO $pdo): void
{
    $id_competicion = validateIntPositive(filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT));
    $id_atleta      = validateIntPositive(filter_input(INPUT_GET, 'id_atleta',      FILTER_VALIDATE_INT));

    if (!$id_competicion && !$id_atleta) {
        http_response_code(400);
        jsonResponse(['error' => 'Se requiere id_competicion o id_atleta']);
        return;
    }

    header('Cache-Control: no-store, max-age=0');

    // GET ?id_atleta=X → historial de rankings del atleta
    if ($id_atleta) {

        // Verificar que el atleta existe
        $check = $pdo->prepare('SELECT id_atleta FROM atleta WHERE id_atleta = ?');
        $check->execute([$id_atleta]);
        if (!$check->fetch()) {
            http_response_code(404);
            jsonResponse(['error' => 'Atleta no encontrado']);
            return;
        }

        $pagination = getPaginationParams(20, 100);

        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    c.nombre_evento, c.fecha,
                    fn_estado_competicion(c.id_competicion) AS estado_competicion,
                    cat.nombre AS categoria,
                    rf.ranking_final AS puesto,
                    rf.media_ranking,
                    rf.num_jueces,
                    rf.fecha_calculo
               FROM resultado_final rf
               JOIN inscripcion i ON i.id_inscripcion = rf.id_inscripcion
               JOIN competicion c ON c.id_competicion = rf.id_competicion
               LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
              WHERE i.id_atleta = ?
              ORDER BY c.fecha DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$id_atleta, $pagination['limit'], $pagination['offset']]);
        paginatedResponse($pdo, $stmt->fetchAll(), $pagination);
        return;
    }

    // GET ?id_competicion=X → podio completo agrupado por categoría
    // Verificar que la competición existe
    $checkComp = $pdo->prepare('SELECT id_competicion FROM competicion WHERE id_competicion = ?');
    $checkComp->execute([$id_competicion]);
    if (!$checkComp->fetch()) {
        http_response_code(404);
        jsonResponse(['error' => 'Competición no encontrada']);
        return;
    }

    // Filtro opcional por categoría
    $categoria = filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS);
    $pagination = getPaginationParams(50, 200);

    if ($categoria) {
        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    cat.nombre AS categoria,
                    rf.ranking_final AS puesto,
                    CONCAT(a.nombre, " ", a.apellido) AS atleta,
                    a.nacionalidad,
                    rf.media_ranking,
                    rf.num_jueces,
                    rf.fecha_calculo
               FROM resultado_final rf
               JOIN inscripcion i ON i.id_inscripcion = rf.id_inscripcion
               JOIN atleta      a ON a.id_atleta      = i.id_atleta
               LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
              WHERE rf.id_competicion = ?
                AND cat.nombre = ?
              ORDER BY rf.ranking_final
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$id_competicion, $categoria, $pagination['limit'], $pagination['offset']]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT SQL_CALC_FOUND_ROWS
                    cat.nombre AS categoria,
                    rf.ranking_final AS puesto,
                    CONCAT(a.nombre, " ", a.apellido) AS atleta,
                    a.nacionalidad,
                    rf.media_ranking,
                    rf.num_jueces,
                    rf.fecha_calculo
               FROM resultado_final rf
               JOIN inscripcion i ON i.id_inscripcion = rf.id_inscripcion
               JOIN atleta      a ON a.id_atleta      = i.id_atleta
               LEFT JOIN categoria cat ON cat.id_categoria = rf.id_categoria
              WHERE rf.id_competicion = ?
              ORDER BY cat.nombre, rf.ranking_final
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$id_competicion, $pagination['limit'], $pagination['offset']]);
    }

    $data  = $stmt->fetchAll();
    $total = (int) $pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

    if (empty($data)) {
        http_response_code(404);
        jsonResponse(['error' => 'Sin resultados para esta competición. ¿Ya se calcularon?']);
        return;
    }

    header('X-Total-Count: ' . $total);
    jsonResponse([
        'data'       => $data,
        'pagination' => [
            'page'        => $pagination['page'],
            'limit'       => $pagination['limit'],
            'total'       => $total,
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ],
    ]);
}

// -------------------------------------------------------
// POST — calcular resultados via sp_calcular_resultados
// El sp_ valida que la competición esté en_curso o cerrada
// -------------------------------------------------------
function handleResPost(PDO $pdo, array $payload, string $raw_body): void
{
    $data = validateJsonBody($raw_body);
    if (!$data) {
        http_response_code(400);
        jsonResponse(['error' => 'El cuerpo debe ser JSON válido']);
        return;
    }

    $id_competicion = validateIntPositive($data['id_competicion'] ?? null);
    if (!$id_competicion) {
        http_response_code(400);
        jsonResponse(['error' => 'id_competicion debe ser un entero positivo']);
        return;
    }

    $ip = getClientIp();

    try {
        $stmt = $pdo->prepare('CALL sp_calcular_resultados(:id, :ip)');
        $stmt->execute([
            ':id' => $id_competicion,
            ':ip' => $ip,
        ]);

        // sp_calcular_resultados devuelve el podio directamente
        $resultados = $stmt->fetchAll();

        jsonResponse([
            'mensaje'    => 'Resultados calculados correctamente',
            'resultados' => $resultados,
        ], 200);

    } catch (PDOException $e) {
        logError('resultados POST', $e);
        http_response_code(422);
        jsonResponse(['error' => cleanSpError($e->getMessage())]);
    }
}

// -------------------------------------------------------
// DELETE — limpiar resultados de una competición
// Solo si la competición está abierta o sin_fecha
// No tiene sentido borrar resultados de un evento cerrado
// -------------------------------------------------------
function handleResDelete(PDO $pdo): void
{
    $id_competicion = validateIntPositive(filter_input(INPUT_GET, 'id_competicion', FILTER_VALIDATE_INT));
    if (!$id_competicion) {
        http_response_code(400);
        jsonResponse(['error' => 'id_competicion requerido']);
        return;
    }

    // Verificar estado via fn_estado_competicion
    $estadoStmt = $pdo->prepare(
        'SELECT fn_estado_competicion(id_competicion) AS estado
           FROM competicion WHERE id_competicion = ?'
    );
    $estadoStmt->execute([$id_competicion]);
    $row = $estadoStmt->fetch();

    if (!$row) {
        http_response_code(404);
        jsonResponse(['error' => 'Competición no encontrada']);
        return;
    }

    // No permitir borrar resultados de eventos cerrados o en curso
    if (in_array($row['estado'], ['cerrada', 'en_curso'], true)) {
        http_response_code(409);
        jsonResponse(['error' => 'No se pueden eliminar resultados de una competición cerrada o en curso']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM resultado_final WHERE id_competicion = ?');
    $stmt->execute([$id_competicion]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        jsonResponse(['error' => 'No hay resultados para esta competición']);
        return;
    }

    jsonResponse([
        'mensaje'         => 'Resultados eliminados correctamente',
        'filas_afectadas' => $stmt->rowCount(),
    ]);
}
