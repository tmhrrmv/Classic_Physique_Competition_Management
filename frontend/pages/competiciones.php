<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competiciones – Gestión Competiciones</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">Gestión Competiciones</a>
    <ul class="navbar-nav">
        <li><a href="competiciones.php" class="active">Competiciones</a></li>
        <li><a href="inscripciones.php">Inscripciones</a></li>
        <li><a href="puntuaciones.php">Puntuaciones</a></li>
        <li><a href="resultados.php">Resultados</a></li>
    </ul>
    <button id="btn-logout" class="btn btn-outline">Cerrar sesión</button>
</nav>

<main class="container">
    <div class="page-header">
        <h2>Competiciones</h2>
        <button class="btn btn-primary" id="btn-nueva">+ Nueva competición</button>
    </div>

    <div id="tabla-wrapper">
        <table class="data-table" id="tabla-competiciones">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre del evento</th>
                    <th>Fecha</th>
                    <th>Lugar</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-competiciones">
                <tr><td colspan="5">Cargando…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Modal alta/edición -->
    <dialog id="modal-competicion">
        <form method="dialog" id="form-competicion">
            <h3 id="modal-titulo">Nueva competición</h3>
            <input type="hidden" id="field-id">
            <div class="form-group">
                <label for="field-nombre">Nombre del evento *</label>
                <input type="text" id="field-nombre" required>
            </div>
            <div class="form-group">
                <label for="field-fecha">Fecha</label>
                <input type="date" id="field-fecha">
            </div>
            <div class="form-group">
                <label for="field-lugar">Lugar</label>
                <input type="text" id="field-lugar">
            </div>
            <div class="modal-actions">
                <button type="button" id="btn-guardar" class="btn btn-primary">Guardar</button>
                <button value="cancel" class="btn btn-outline">Cancelar</button>
            </div>
        </form>
    </dialog>
</main>

<script src="../js/api.js"></script>
<script src="../js/auth.js"></script>
</body>
</html>
