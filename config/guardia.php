<?php
/**
 * Guardia de sesión y roles - Hotel Costa Azul
 * Ruta: HOTEL_COSTAZUL/config/guardia.php
 *
 * Uso al inicio de cada página protegida:
 *   require_once '<ruta>/config/guardia.php';
 *   requerir_rol(['Huesped']);
 */
const URL_BASE_LOGIN = '/HOTEL_COSTAZUL/Huesped/Login_regis/index.php';

function requerir_rol(array $rolesPermitidos): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        header('Location: ' . URL_BASE_LOGIN);
        exit();
    }
}