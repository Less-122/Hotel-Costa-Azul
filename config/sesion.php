<?php
/**
 * Control de sesión y permisos por rol - Hotel Costa Azul
 *
 * Roles (se comparan sin importar mayúsculas):
 *   admin, recepcionista, camarista  -> personal (entran por personal_login)
 *   huesped                          -> cliente  (entra por Huesped/Login_regis/login.php)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Ruta base de la aplicación (ej. /Hotel-Costa-Azul/), calculada a partir de la carpeta real. */
function urlBase(): string {
    $raiz = realpath(__DIR__ . '/..');
    $doc  = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($raiz !== false && $doc !== false) {
        $raiz = str_replace('\\', '/', $raiz);
        $doc  = str_replace('\\', '/', $doc);
        if (stripos($raiz, $doc) === 0) {
            return rtrim(substr($raiz, strlen($doc)), '/') . '/';
        }
    }
    return '/Hotel-Costa-Azul/';
}

/** Rol de la sesión actual en minúsculas ('' si no hay sesión). */
function rolActual(): string {
    return isset($_SESSION['id_usuario']) ? strtolower((string)($_SESSION['rol'] ?? '')) : '';
}

/** Página de inicio de cada rol. */
function paginaInicioRol(string $rol): string {
    $inicio = [
        'admin'         => 'Administrador/Tarifas/tari.php',
        'recepcionista' => 'Hotel_Recep/Recepcion/recep.php',
        'camarista'     => 'Camarista/Cama.php',
        'huesped'       => 'Huesped/Reserva/Reser.php',
    ];
    return urlBase() . ($inicio[$rol] ?? 'Principal/index.php');
}

/**
 * Deja pasar solo a los roles indicados; a los demás los redirige y corta la ejecución.
 *   verificarAcceso(['admin'])
 *   verificarAcceso(['recepcionista', 'admin'])
 *   verificarAcceso('huesped')
 *
 * - Sin sesión       -> al login que corresponde (personal o huésped).
 * - Con otro rol     -> a la página de inicio de su propio rol (un huésped que escribe
 *                       la URL de una pantalla del personal NO entra).
 */
function verificarAcceso($rolesPermitidos): void {
    $permitidos = array_map('strtolower', (array)$rolesPermitidos);
    $rol = rolActual();

    // Estas páginas nunca deben quedar en caché (evita ver el panel con el botón "Atrás" tras salir)
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($rol === '') {
        $esPersonal = (bool) array_intersect($permitidos, ['admin', 'recepcionista', 'camarista']);
        $login = $esPersonal
            ? 'personal_login/login_personal.php'
            : 'Huesped/Login_regis/login.php';
        header('Location: ' . urlBase() . $login . '?error=acceso_denegado');
        exit;
    }

    if (!in_array($rol, $permitidos, true)) {
        header('Location: ' . paginaInicioRol($rol));
        exit;
    }
}
