<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntuaciones – Gestión Competiciones</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">Gestión Competiciones</a>
    <ul class="navbar-nav">
        <li><a href="competiciones.php">Competiciones</a></li>
        <li><a href="inscripciones.php">Inscripciones</a></li>
        <li><a href="puntuaciones.php" class="active">Puntuaciones</a></li>
        <li><a href="resultados.php">Resultados</a></li>
    </ul>
    <button id="btn-logout" class="btn btn-outline">Cerrar sesión</button>
</nav>

<main class="container">
    <h2>Puntuaciones</h2>

    <div class="filter-bar">
        <label for="filtro-competicion">Competición:</label>
        <select id="filtro-competicion">
            <option value="">— Selecciona —</option>
        </select>
    </div>

    <div id="tabla-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Atleta</th>
                    <th>Juez</th>
                    <th>Licencia</th>
                    <th>Ranking otorgado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-puntuaciones">
                <tr><td colspan="5">Selecciona una competición.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<script src="../js/api.js"></script>
<script src="../js/auth.js"></script>
</body>
</html>
