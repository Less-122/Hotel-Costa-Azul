<?php
/**
 * Control de sesiones y roles - Hotel Costa Azul.
 * Los roles coinciden con USUARIO.rol: Huesped, Recepcionista, Camarista, Admin.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica que haya sesión iniciada y que el rol sea uno de los permitidos.
 * @param string|array $rolesPermitidos Un rol o lista de roles.
 */
function verificarAcceso($rolesPermitidos): void
{
    $roles = (array) $rolesPermitidos;
    if (!isset($_SESSION['id_usuario'], $_SESSION['rol']) || !in_array($_SESSION['rol'], $roles, true)) {
        // Ruta absoluta de la carpeta del proyecto; se puede cambiar con APP_BASE.
        $base = getenv('APP_BASE') !== false ? getenv('APP_BASE') : '/Hotel-Costa-Azul';
        header('Location: ' . $base . '/Huesped/Login_regis/index.php?error=acceso_denegado');
        exit();
    }
}
