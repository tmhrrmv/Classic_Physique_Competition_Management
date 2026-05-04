<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Gestión Competiciones</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <img src="../assets/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
        Gestión Competiciones
    </a>
    <ul class="navbar-nav">
        <li><a href="competiciones.php">Competiciones</a></li>
        <li><a href="inscripciones.php">Inscripciones</a></li>
        <li><a href="puntuaciones.php">Puntuaciones</a></li>
        <li><a href="resultados.php">Resultados</a></li>
    </ul>
    <button id="btn-logout" class="btn btn-outline">Cerrar sesión</button>
</nav>

<main class="container">
    <h2>Panel principal</h2>

    <div class="stats-grid" id="stats-grid">
        <div class="stat-card">
            <span class="stat-value" id="stat-competiciones">–</span>
            <span class="stat-label">Competiciones</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" id="stat-atletas">–</span>
            <span class="stat-label">Atletas</span>
        </div>
        <div class="stat-card">
            <span class="stat-value" id="stat-inscripciones">–</span>
            <span class="stat-label">Inscripciones</span>
        </div>
    </div>

    <section id="proximas-competiciones">
        <h3>Próximas competiciones</h3>
        <div id="lista-competiciones">Cargando…</div>
    </section>
</main>

<script src="../js/api.js"></script>
<script src="../js/auth.js"></script>
<script src="../js/dashboard.js"></script>
</body>
</html>
