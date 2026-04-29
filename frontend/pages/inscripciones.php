<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripciones – Gestión Competiciones</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">Gestión Competiciones</a>
    <ul class="navbar-nav">
        <li><a href="competiciones.php">Competiciones</a></li>
        <li><a href="inscripciones.php" class="active">Inscripciones</a></li>
        <li><a href="puntuaciones.php">Puntuaciones</a></li>
        <li><a href="resultados.php">Resultados</a></li>
    </ul>
    <button id="btn-logout" class="btn btn-outline">Cerrar sesión</button>
</nav>

<main class="container">
    <div class="page-header">
        <h2>Inscripciones</h2>
        <button class="btn btn-primary" id="btn-nueva">+ Inscribir atleta</button>
    </div>

    <div class="filter-bar">
        <label for="filtro-competicion">Filtrar por competición:</label>
        <select id="filtro-competicion">
            <option value="">— Todas —</option>
        </select>
    </div>

    <div id="tabla-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Atleta</th>
                    <th>Competición</th>
                    <th>Categoría</th>
                    <th>Dorsal</th>
                    <th>Peso (kg)</th>
                    <th>Estatura (m)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbody-inscripciones">
                <tr><td colspan="7">Selecciona una competición para ver las inscripciones.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<script src="../js/api.js"></script>
<script src="../js/auth.js"></script>
</body>
</html>
