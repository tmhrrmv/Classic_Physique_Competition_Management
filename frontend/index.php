<?php
session_start();

// Redirige al dashboard si ya hay sesión, o al login si no
if (isset($_SESSION['token'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/login.php');
}
exit;
