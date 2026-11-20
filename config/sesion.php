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

require_once __DIR__ . '/conexion.php';

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

/** Cierra y limpia la sesión actual por completo. */
function cerrarSesionCompleta(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Login correspondiente según el conjunto de roles permitidos. */
function loginPorRoles(array $permitidos): string {
    $esPersonal = (bool) array_intersect($permitidos, ['admin', 'recepcionista', 'camarista']);
    return urlBase() . ($esPersonal
        ? 'personal_login/login_personal.php'
        : 'Huesped/Login_regis/login.php');
}

/** Obtiene o genera el token CSRF de la sesión actual. */
function obtenerCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Devuelve el input hidden listo para insertar dentro de cualquier formulario POST. */
function campoCsrf(): string {
    $token = htmlspecialchars(obtenerCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/** Valida de forma segura contra timing attacks el token CSRF recibido por POST. */
function validarCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        !is_string($token) ||
        $token === ''
    ) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sincroniza el rol actual del usuario con la tabla `usuario` cada cierto intervalo
 * para reflejar cambios de rol o eliminación de cuenta sin consultar en cada petición.
 */
function sincronizarUsuarioConBD(int $intervaloSegundos = 15): bool {
    if (empty($_SESSION['id_usuario'])) {
        return false;
    }

    $ahora = time();
    $ultimaVerif = (int) ($_SESSION['ultima_verificacion_bd'] ?? 0);
    if (($ahora - $ultimaVerif) < $intervaloSegundos) {
        return true;
    }

    try {
        $pdo = obtenerConexion();
        $st = $pdo->prepare("SELECT rol, nombre FROM usuario WHERE id_usuario = ? LIMIT 1");
        $st->execute([(int) $_SESSION['id_usuario']]);
        $usuarioBD = $st->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioBD) {
            return false;
        }

        $_SESSION['rol'] = $usuarioBD['rol'];
        if (!empty($usuarioBD['nombre'])) {
            $_SESSION['nombre'] = $usuarioBD['nombre'];
        }
        $_SESSION['ultima_verificacion_bd'] = $ahora;
        return true;
    } catch (PDOException $e) {
        error_log('Error al sincronizar sesión con BD: ' . $e->getMessage());
        // Si hay un fallo temporal de BD, no tumbamos la sesión activa de golpe
        return true;
    }
}

/**
 * Deja pasar solo a los roles indicados; a los demás los redirige y corta la ejecución.
 *   verificarAcceso(['admin'])
 *   verificarAcceso(['recepcionista', 'admin'])
 *   verificarAcceso('huesped')
 */
function verificarAcceso($rolesPermitidos): void {
    $permitidos = array_map('strtolower', (array)$rolesPermitidos);
    $rol = rolActual();

    // Estas páginas nunca deben quedar en caché (evita ver el panel con el botón "Atrás" tras salir)
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($rol === '') {
        header('Location: ' . loginPorRoles($permitidos) . '?error=acceso_denegado');
        exit;
    }

    if (!sincronizarUsuarioConBD(15)) {
        $destinoLogin = loginPorRoles($permitidos);
        cerrarSesionCompleta();
        header('Location: ' . $destinoLogin . '?error=cuenta_inactiva');
        exit;
    }

    $rol = rolActual();
    if (!in_array($rol, $permitidos, true)) {
        header('Location: ' . paginaInicioRol($rol));
        exit;
    }
}