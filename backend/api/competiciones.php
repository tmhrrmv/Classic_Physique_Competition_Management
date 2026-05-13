// -------------------------------------------------------
// DELETE — eliminar competición
// CAMBIO v1.2: Se permite eliminar cualquier competición
// incluyendo las cerradas. Solo admin puede hacerlo.
// El modal del frontend ya pide confirmar escribiendo el nombre.
// ON DELETE CASCADE en inscripcion y resultado_final
// elimina automáticamente todos los datos relacionados.
// -------------------------------------------------------
function handleCompDelete(PDO $pdo): void
{
    $id = validateIntPositive(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT));
    if (!$id) {
        http_response_code(400);
        jsonResponse(['error' => 'ID de competición requerido']);
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM competicion WHERE id_competicion = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            jsonResponse(['error' => 'Competición no encontrada']);
            return;
        }

        jsonResponse(['mensaje' => 'Competición eliminada correctamente']);

    } catch (PDOException $e) {
        logError('competiciones DELETE', $e);
        http_response_code(500);
        jsonResponse(['error' => 'Error interno del servidor']);
    }
}
