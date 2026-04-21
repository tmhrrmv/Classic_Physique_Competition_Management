<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión – Gestión Competiciones</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="page-login">

<div class="login-container">
    <div class="login-card">
        <img src="../assets/logo.png" alt="Logo" class="login-logo" onerror="this.style.display='none'">
        <h1>Gestión de Competiciones</h1>

        <form id="form-login">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <p id="login-error" class="error-msg" hidden></p>
            <button type="submit" class="btn btn-primary btn-full">Entrar</button>
        </form>
    </div>
</div>

<script src="../js/auth.js"></script>
</body>
</html>
