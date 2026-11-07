<?php
session_start();

// Si ya inició sesión, lo mandamos a su pantalla según el rol
if (isset($_SESSION['rol'])) {
    header('Location: ' . ($_SESSION['rol'] === 'Huesped'
        ? '../Reserva/Reser.php'
        : '../../Principal/index.php'));
    exit();
}

// Mensajes que deja el controlador (se muestran una sola vez)
$errorMsg = $_SESSION['flash_error'] ?? '';
$okMsg    = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_ok']);
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

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger" style="color: #d9534f; background-color: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          <?php echo htmlspecialchars($errorMsg); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($okMsg)): ?>
        <div class="alert alert-success" style="color: #3c763d; background-color: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          <?php echo htmlspecialchars($okMsg); ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" class="login-form" method="POST" action="../controladores/auth_controller.php">
        <input type="hidden" name="operacion" value="login">

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
        ¿Eres huésped y aún no tienes cuenta? <a href="regis.php">Crear cuenta</a>
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