<?php
session_start();

// Si ya inició sesión, lo redirigimos según su rol
if (isset($_SESSION['rol'])) {
    header('Location: ' . ($_SESSION['rol'] === 'Huesped'
        ? '../Reserva/Reser.php'
        : '../../Principal/index.php'));
    exit();
}

// Mensaje que deja el controlador (se muestra una sola vez)
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Crear cuenta</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Tu CSS -->
  <link rel="stylesheet" href="regis.css">
</head>
<body>

  <div class="register-container">

    <!-- Lado Izquierdo: Imagen -->
    <div class="hero-section">
      <div class="hero-content">
        <h1>Hotel Costa Azul</h1>
        <p>Tu descanso empieza aquí.</p>
      </div>
    </div>

    <!-- Lado Derecho: Formulario -->
    <div class="form-section">

      <div class="header-brand">
        <h2>Hotel Costa Azul</h2>
      </div>

      <div class="welcome-text">
        <h1>Crear cuenta</h1>
        <p>Registra tus datos para acceder al sistema.</p>
      </div>

      <!-- Alertas de error (vienen del controlador) -->
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="color: #d9534f; background-color: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <!-- Formulario conectado al controlador -->
      <form id="registerForm" class="register-form" method="POST" action="../controladores/auth_controller.php" onsubmit="return validarContrasenas();">

        <!-- Operación para el controlador -->
        <input type="hidden" name="operacion" value="registro">

        <!-- Nombre completo -->
        <div class="input-group">
          <label for="fullname">Nombre completo</label>
          <input type="text" id="fullname" name="nombre" placeholder="Ej. María López Pérez" required>
        </div>

        <!-- Teléfono -->
        <div class="form-row">
          <div class="input-group" style="width: 100%;">
            <label for="phone">Teléfono (10 dígitos)</label>
            <input type="tel" id="phone" name="telefono" placeholder="7771234567" pattern="[0-9]{10}" maxlength="10" required>
          </div>
        </div>

        <!-- Correo electrónico -->
        <div class="input-group">
          <label for="email">Correo electrónico (para iniciar sesión)</label>
          <input type="email" id="email" name="correo" placeholder="correo@ejemplo.com" required>
        </div>

        <!-- Contraseña + Confirmar contraseña -->
        <div class="form-row">
          <div class="input-group">
            <label for="password">Contraseña</label>
            <div class="input-wrapper">
              <input type="password" id="password" name="password" minlength="8" required>
              <i class="fa-regular fa-eye icon-toggle-password" id="togglePassword"></i>
            </div>
          </div>

          <div class="input-group">
            <label for="confirmPassword">Confirmar contraseña</label>
            <div class="input-wrapper">
              <!-- Ahora lleva name para que el servidor también la valide -->
              <input type="password" id="confirmPassword" name="confirmar_password" minlength="8" required>
              <i class="fa-regular fa-eye icon-toggle-password" id="toggleConfirmPassword"></i>
            </div>
          </div>
        </div>

        <p class="form-help-text" id="passwordError" style="color: red; display: none;">Las contraseñas no coinciden.</p>
        <p class="form-help-text">Mínimo 8 caracteres.</p>

        <button type="submit" class="btn-submit">Crear cuenta</button>

      </form>

      <!-- Enlace para volver al login (ruta corregida) -->
      <div class="form-footer">
        ¿Ya tienes cuenta? <a href="index.php">Iniciar sesión</a>
      </div>

    </div>

  </div>

  <script>
    function validarContrasenas() {
      const pass = document.getElementById('password').value;
      const confirmPass = document.getElementById('confirmPassword').value;
      const errorText = document.getElementById('passwordError');

      if (pass !== confirmPass) {
        errorText.style.display = 'block';
        return false;
      }
      errorText.style.display = 'none';
      return true;
    }

    document.getElementById('togglePassword').addEventListener('click', function () {
      const p = document.getElementById('password');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
      const p = document.getElementById('confirmPassword');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>