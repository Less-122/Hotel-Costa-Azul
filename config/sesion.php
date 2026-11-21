<?php
/**
 * Control de sesión y permisos por rol - Hotel Costa Azul
 *
 * Roles (se comparan sin importar mayúsculas):
 *   admin, recepcionista, camarista  -> personal (entran por personal_login)
 *   huesped                          -> cliente  (entra por Huesped/Login_regis/login.php)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/conexion.php';

const LOGIN_MAX_INTENTOS     = 5;
const LOGIN_VENTANA_SEGUNDOS = 900; // 15 minutos de bloqueo

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
 * Construye una clave única para rastrear intentos de inicio de sesión por contexto, correo e IP.
 */
function claveIntentoLogin(string $contexto, string $identificador = ''): string {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $idNormalizado = mb_strtolower(trim($identificador), 'UTF-8');
    return hash('sha256', $contexto . '|' . $idNormalizado . '|' . $ip);
}

/**
 * Devuelve la clave de seguimiento correspondiente: si hay identificador (correo),
 * el contador pertenece exclusivamente a esa cuenta en ese contexto e IP.
 */
function claveRastreoPorCuenta(string $contexto, string $identificador = ''): string {
    $idLimpio = mb_strtolower(trim($identificador), 'UTF-8');
    return claveIntentoLogin($contexto, $idLimpio);
}

/**
 * Lee el registro persistente de intentos de login desde el directorio temporal del sistema.
 */
function leerEstadoIntentoLogin(string $clave): array {
    $archivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hca_login_' . $clave . '.json';
    if (!is_file($archivo)) {
        return ['intentos' => 0, 'bloqueado_hasta' => 0];
    }
    $contenido = @file_get_contents($archivo);
    $datos = is_string($contenido) ? json_decode($contenido, true) : null;
    if (!is_array($datos)) {
        return ['intentos' => 0, 'bloqueado_hasta' => 0];
    }
    return [
        'intentos'        => (int) ($datos['intentos'] ?? 0),
        'bloqueado_hasta' => (int) ($datos['bloqueado_hasta'] ?? 0),
    ];
}

/**
 * Guarda el estado de intentos de inicio de sesión en almacenamiento temporal.
 */
function guardarEstadoIntentoLogin(string $clave, array $estado): void {
    $archivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hca_login_' . $clave . '.json';
    @file_put_contents($archivo, json_encode($estado), LOCK_EX);
}

/**
 * Verifica si la cuenta/identificador está temporalmente bloqueado por exceso de intentos fallidos.
 * Devuelve ['bloqueado' => bool, 'segundos_restantes' => int, 'mensaje' => string].
 */
function verificarBloqueoLogin(string $contexto, string $identificador = ''): array {
    $ahora = time();
    $clave = claveRastreoPorCuenta($contexto, $identificador);
    $estado = leerEstadoIntentoLogin($clave);

    if ($estado['bloqueado_hasta'] > $ahora) {
        $restante = $estado['bloqueado_hasta'] - $ahora;
        $minutos = (int) ceil($restante / 60);
        return [
            'bloqueado'          => true,
            'segundos_restantes' => $restante,
            'mensaje'            => "Demasiados intentos fallidos. Por seguridad, espera {$minutos} minuto(s) antes de volver a intentarlo.",
        ];
    }

    return [
        'bloqueado'          => false,
        'segundos_restantes' => 0,
        'mensaje'            => '',
    ];
}

/**
 * Registra un intento fallido de inicio de sesión para la cuenta indicada y bloquea al alcanzar LOGIN_MAX_INTENTOS.
 * Devuelve los intentos restantes antes del bloqueo (0 si acaba de bloquearse).
 */
function registrarIntentoFallidoLogin(string $contexto, string $identificador = ''): int {
    $ahora = time();
    $clave = claveRastreoPorCuenta($contexto, $identificador);
    $estado = leerEstadoIntentoLogin($clave);

    if ($estado['bloqueado_hasta'] > 0 && $estado['bloqueado_hasta'] <= $ahora) {
        $estado = ['intentos' => 0, 'bloqueado_hasta' => 0];
    }

    $estado['intentos']++;
    if ($estado['intentos'] >= LOGIN_MAX_INTENTOS) {
        $estado['bloqueado_hasta'] = $ahora + LOGIN_VENTANA_SEGUNDOS;
        $restantes = 0;
    } else {
        $restantes = LOGIN_MAX_INTENTOS - $estado['intentos'];
    }

    guardarEstadoIntentoLogin($clave, $estado);
    return $restantes;
}

/**
 * Limpia el contador de intentos fallidos de la cuenta tras un inicio de sesión exitoso.
 */
function limpiarIntentosLogin(string $contexto, string $identificador = ''): void {
    $clave = claveRastreoPorCuenta($contexto, $identificador);
    $archivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hca_login_' . $clave . '.json';
    if (is_file($archivo)) {
        @unlink($archivo);
    }
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