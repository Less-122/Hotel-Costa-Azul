<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

// Si ya hay sesión iniciada, mandamos a cada quien a su página
if (isset($_SESSION['id_usuario'])) {
    header("Location: " . paginaInicioRol(rolActual()));
    exit();
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($correo) && !empty($password)) {
        try {
            $sql = "SELECT id_usuario, nombre, password, rol FROM usuario WHERE correo = :correo";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':correo', $correo);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Solo se acepta el hash guardado en la BD (password_hash / password_verify)
            if ($usuario && password_verify($password, $usuario['password'])) {
                if (strtolower($usuario['rol']) !== 'huesped') {
                    $errorMsg = "Esta cuenta es del personal del hotel. Ingresa por el portal de personal.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['nombre'] = $usuario['nombre'];
                    $_SESSION['rol'] = 'Huesped';

                    header("Location: ../Reserva/Reser.php");
                    exit();
                }
            } else {
                $errorMsg = "Correo o contraseña incorrectos. Revisa tus datos.";
            }
        } catch (PDOException $e) {
            error_log('Login huésped: ' . $e->getMessage());
            $errorMsg = "Error en el sistema. Inténtalo de nuevo.";
        }
    } else {
        $errorMsg = "Por favor, completa todos los campos.";
    }
}

if (isset($_GET['error']) && $errorMsg === '') {
    if ($_GET['error'] === 'credenciales') {
        $errorMsg = "Correo o contraseña incorrectos. Por favor, intenta de nuevo.";
    } elseif ($_GET['error'] === 'acceso_denegado') {
        $errorMsg = "Inicia sesión para continuar.";
    }
}
$registroOk = (($_GET['registro'] ?? '') === 'ok');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Acceso al Sistema</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="login-container">
    <div class="hero-section">
      <div class="hero-content">
        <h1>Hotel Costa Azul</h1>
        <p>Tu descanso empieza aquí.</p>
      </div>
    </div>
    <div class="form-section">
      <div class="header-brand"><h2>Hotel Costa Azul</h2></div>
      <div class="welcome-text">
        <h1>Bienvenido de nuevo</h1>
        <p>Ingresa con tu cuenta para continuar.</p>
      </div>

      <?php if ($registroOk): ?>
        <div class="alert alert-success" style="color: #3c763d; background-color: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          ¡Cuenta creada! Ya puedes iniciar sesión.
        </div>
      <?php endif; ?>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger" style="color: #d9534f; background-color: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          <?php echo htmlspecialchars($errorMsg); ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" class="login-form" method="POST" action="">
        <div class="input-group">
          <label for="username">Correo electrónico / Usuario</label>
          <div class="input-wrapper">
            <i class="fa-regular fa-user icon-left"></i>
            <input type="text" id="username" name="correo" placeholder="Ej. huesped.a@correo.test" required>
          </div>
        </div>
        <div class="input-group">
          <label for="password">Contraseña</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" id="password" name="password" required>
            <i class="fa-regular fa-eye icon-toggle-password" id="togglePassword"></i>
          </div>
        </div>
        <button type="submit" class="btn-submit">Iniciar sesión</button>
      </form>
      <div class="form-footer">
        ¿Aún no tienes cuenta? <a href="regis.php">Crear cuenta</a>
      </div>
    </div>
  </div>
  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
      });
    }
  </script>
</body>
</html>