<?php
session_start();
require_once "../config/conexion.php";

$base_url = "/Hotel-Costa-Azul/";

// Ruta de cada rol. Si un rol no está aquí, no tiene acceso (huesped incluido).
$rutas = [
    'admin'         => 'Administrador/Tarifas/tari.php',
    'camarista'     => 'Camarista/Cama.php',
    'recepcionista' => 'Hotel_Recep/Recepcion/recep.php',
];

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo   = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($correo === '' || $password === '') {
        $error = "Complete todos los campos.";
    } else {
        $stmt = $conexion->prepare(
            "SELECT id_usuario, nombre, password, rol FROM usuario WHERE correo = :correo"
        );
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $error = "Correo o contraseña incorrectos.";
        } else {
            $rol = strtolower($usuario['rol']);

            if (!isset($rutas[$rol])) {
                $error = "Este portal es exclusivo para el personal del hotel.";
            } else {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['rol']        = $rol;

                header("Location: " . $base_url . $rutas[$rol]);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Acceso de Personal</title>
    <!-- Enlace a los estilos -->
    <link rel="stylesheet" href="login_personal.css">
    <!-- FontAwesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Sección Izquierda Decorativa -->
        <div class="hero-section">
            <div class="hero-content">
                <h1>Control y Operación</h1>
                <p>Plataforma exclusiva para la gestión interna del Hotel Costa Azul.</p>
            </div>
        </div>

        <!-- Sección Derecha: Formulario -->
        <div class="form-section">
            <div class="header-brand">
                <h2>Hotel Costa Azul</h2>
                <span>Portal de Personal</span>
            </div>

            <div class="welcome-text">
                <h1>Iniciar Sesión</h1>
                <p>Ingrese sus credenciales institucionales para acceder al sistema.</p>
            </div>

            <!-- Mostrar mensaje de error / acceso denegado si existe -->
            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login_personal.php" method="POST" class="login-form">
                <div class="input-group">
                    <label for="correo">Correo Electrónico</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope icon-left"></i>
                        <input type="email" id="correo" name="correo" placeholder="correo@hotelcostaazul.com" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock icon-left"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <i class="fa-solid fa-eye icon-toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Acceder al Sistema</button>
            </form>

            <div class="form-footer">
                <p>&copy; 2026 Hotel Costa Azul. Uso Restringido.</p>
            </div>
        </div>
    </div>

    <!-- Script de funcionalidad -->
    <script src="login_personal.js"></script>
</body>
</html>