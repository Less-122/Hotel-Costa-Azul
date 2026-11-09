<?php
/**
 * Archivo de Control de Sesiones y Autenticación - Hotel Costa Azul
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAcceso($rolPermitido) {
    if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== $rolPermitido) {
        header("Location: /HOTEL_COSTA/Huesped/Login_regis/index.php?error=acceso_denegado");
        exit();
    }
}
 ?>
