<?php
/**
 * Controlador de autenticación - Hotel Costa Azul
 * Ruta: HOTEL_COSTAZUL/Huesped/controladores/auth_controller.php
 *
 * Operaciones (campo oculto "operacion"):
 *   login    -> inicia sesión (POST)
 *   registro -> crea cuenta de Huésped (POST)
 *   logout   -> cierra sesión (GET o POST)
 */
require_once '../../config/conexion.php';
session_start();

// ====== Rutas relativas a Huesped/controladores/ ======
const URL_LOGIN    = '../Login_regis/index.php';
const URL_REGISTRO = '../Login_regis/regis.php';
const URL_HUESPED  = '../Reserva/Reser.php';
const URL_PERSONAL = '../../Principal/index.php';
// ===================================================================

/** Guarda un mensaje (error u ok) en sesión y redirige. */
function volver(string $url, string $mensaje = '', string $tipo = 'error'): void
{
    if ($mensaje !== '') {
        $_SESSION['flash_' . $tipo] = $mensaje;
    }
    header("Location: $url");
    exit();
}

$operacion = $_REQUEST['operacion'] ?? '';

// Solo "logout" se permite por GET; login y registro exigen POST
if ($operacion !== 'logout' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    volver(URL_LOGIN);
}

switch ($operacion) {

    /* ---------------------- INICIAR SESIÓN ---------------------- */
    case 'login':
        $correo   = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($correo === '' || $password === '') {
            volver(URL_LOGIN, 'Por favor, completa todos los campos.');
        }

        try {
            $stmt = $conexion->prepare(
                "SELECT id_usuario, nombre, password, rol FROM usuario WHERE correo = :correo"
            );
            $stmt->execute([':correo' => $correo]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['rol']        = $usuario['rol'];

                volver($usuario['rol'] === 'Huesped' ? URL_HUESPED : URL_PERSONAL);
            }

            volver(URL_LOGIN, 'Correo o contraseña incorrectos. Revisa tus datos.');

        } catch (PDOException $e) {
            error_log('[Hotel Costa Azul] Error en login: ' . $e->getMessage());
            volver(URL_LOGIN, 'Ocurrió un error en el sistema. Intenta de nuevo.');
        }
        break;

    /* ------------------ CREAR CUENTA (solo Huésped) ------------------ */
    case 'registro':
        $nombre    = trim($_POST['nombre'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $correo    = trim($_POST['correo'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirmar = $_POST['confirmar_password'] ?? '';

        if ($nombre === '' || $telefono === '' || $correo === '' || $password === '' || $confirmar === '') {
            volver(URL_REGISTRO, 'Completa todos los campos.');
        }
        if (!preg_match('/^[0-9]{10}$/', $telefono)) {
            volver(URL_REGISTRO, 'El teléfono debe tener 10 dígitos.');
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            volver(URL_REGISTRO, 'El correo no tiene un formato válido.');
        }
        if (strlen($password) < 8) {
            volver(URL_REGISTRO, 'La contraseña debe tener al menos 8 caracteres.');
        }
        if ($password !== $confirmar) {
            volver(URL_REGISTRO, 'Las contraseñas no coinciden.');
        }

        try {
            $stmt = $conexion->prepare("SELECT 1 FROM usuario WHERE correo = :correo");
            $stmt->execute([':correo' => $correo]);
            if ($stmt->fetch()) {
                volver(URL_REGISTRO, 'Ya existe una cuenta con ese correo.');
            }

            // El rol se fija aquí; NUNCA se toma del formulario
            $stmt = $conexion->prepare(
                "INSERT INTO usuario (nombre, contacto, correo, password, rol)
                 VALUES (:nombre, :contacto, :correo, :password, 'Huesped')"
            );
            $stmt->execute([
                ':nombre'   => $nombre,
                ':contacto' => $telefono,
                ':correo'   => $correo,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            volver(URL_LOGIN, 'Cuenta creada. Ya puedes iniciar sesión.', 'ok');

        } catch (PDOException $e) {
            error_log('[Hotel Costa Azul] Error en registro: ' . $e->getMessage());
            volver(URL_REGISTRO, 'No se pudo crear la cuenta. Intenta de nuevo.');
        }
        break;

    /* ------------------------ CERRAR SESIÓN ------------------------ */
    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        volver(URL_LOGIN, 'Sesión cerrada correctamente.', 'ok');
        break;

    default:
        volver(URL_LOGIN);
}