<?php
/**
 * Config/sesion.php
 * Sesión, roles y protección de pantallas. Se incluye al inicio de cada .php:
 *
 *     require_once __DIR__ . '/../../Config/conexion.php';
 *     require_once __DIR__ . '/../../Config/sesion.php';
 *     $usuario = requerirRol(ROL_HUESPED);          // pantalla protegida
 *
 * Los roles son los mismos valores de USUARIO.rol en la base de datos.
 */
declare(strict_types=1);

const ROL_HUESPED       = 'Huesped';
const ROL_RECEPCIONISTA = 'Recepcionista';
const ROL_CAMARISTA     = 'Camarista';
const ROL_ADMIN         = 'Admin';

/** Pantalla de inicio de cada rol (ruta desde la raíz del proyecto). */
const INICIO_POR_ROL = [
    ROL_HUESPED       => 'Principal/index.php',
    ROL_RECEPCIONISTA => 'Hotel_Recep/Recepcion/recep.php',
    ROL_CAMARISTA     => 'Camarista/Cama.php',
    ROL_ADMIN         => 'Administrador/Reportes/repo.php',
];
const RUTA_LOGIN = 'Huesped/Login_regis/index.php';

/* ---------- Sesión segura ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_name('hotel_costa_azul');
    session_set_cookie_params([
        'lifetime' => 0,                                   // se cierra al cerrar el navegador
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),           // solo por HTTPS cuando exista
        'httponly' => true,                                // JavaScript no puede leer la cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Rutas ---------- */

/** Carpeta del proyecto dentro de htdocs (se detecta sola, ej. "/HOTEL_COSTAZUL"). */
function urlBase(): string
{
    static $base = null;
    if ($base === null) {
        $raiz = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
        $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $base = ($doc !== '' && stripos($raiz, $doc) === 0) ? substr($raiz, strlen($doc)) : '';
    }
    return $base;
}

/** URL absoluta de una ruta del proyecto: url('Huesped/Reserva/Reser.php'). */
function url(string $ruta): string
{
    return urlBase() . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

/* ---------- Usuario en sesión ---------- */

/** Devuelve ['id_usuario','nombre','rol'] o null si no hay sesión iniciada. */
function usuarioActual(): ?array
{
    if (empty($_SESSION['id_usuario'])) {
        return null;
    }
    return [
        'id_usuario' => (int) $_SESSION['id_usuario'],
        'nombre'     => (string) $_SESSION['nombre'],
        'rol'        => (string) $_SESSION['rol'],
    ];
}

/** Guarda al usuario tras un login correcto. $fila viene de la tabla usuario. */
function iniciarSesionUsuario(array $fila): void
{
    session_regenerate_id(true);               // evita fijación de sesión
    $_SESSION['id_usuario'] = (int) $fila['id_usuario'];
    $_SESSION['nombre']     = (string) $fila['nombre'];
    $_SESSION['rol']        = (string) $fila['rol'];
}

function cerrarSesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Protección de pantallas ---------- */

function esPeticionJson(): bool
{
    return stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/**
 * Exige sesión y que el rol esté permitido. Devuelve los datos del usuario.
 * - Sin sesión: manda al login (o responde 401 si la petición viene de fetch).
 * - Con otro rol: lo regresa a su propia pantalla de inicio (o responde 403).
 */
function requerirRol(string ...$rolesPermitidos): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        if (esPeticionJson()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok' => false, 'mensaje' => 'Inicia sesión para continuar.']));
        }
        redirigir(RUTA_LOGIN);
    }

    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
        if (esPeticionJson()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok' => false, 'mensaje' => 'No tienes permiso para esta acción.']));
        }
        redirigir(INICIO_POR_ROL[$usuario['rol']] ?? RUTA_LOGIN);
    }

    return $usuario;
}

/* ---------- Utilidades de seguridad ---------- */

/** Escapa texto antes de imprimirlo en HTML: <?= h($nombre) ?> */
function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Token CSRF de la sesión (para formularios y fetch que modifican datos). */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Aborta con 403 si el token recibido (campo "csrf" o encabezado X-CSRF-Token) no coincide. */
function verificarCsrf(): void
{
    $recibido = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($recibido) || !hash_equals(tokenCsrf(), $recibido)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}