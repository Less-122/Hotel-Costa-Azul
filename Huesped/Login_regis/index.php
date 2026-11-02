<?php
session_start();

// Si el usuario ya había iniciado sesión, lo redirigimos automáticamente según su rol
if (isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'admin':
        case 'recepcion':
            header("Location: ../vistas/recepcion/dashboard.php");
            exit();
        case 'camarista':
            header("Location: ../vistas/camaristas/panel.php");
            exit();
        case 'huesped':
        default:
            header("Location: ../vistas/huesped/catalogo.php");
            exit();
    }
}

// Capturar mensajes de error si los hay (ej. ?error=credenciales)
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Acceso al Sistema</title>
  
  <!-- Iconos de FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- CONEXIÓN CON EL ARCHIVO CSS -->
  <link rel="stylesheet" href="login.css">
</head>
<body>

  <div class="login-container">
    
    <!-- Sección Izquierda (Imagen de fondo y texto) -->
    <div class="hero-section">
      <div class="hero-content">
        <h1>Hotel Costa Azul</h1>
        <p>Tu descanso empieza aquí.</p>
      </div>
    </div>

    <!-- Sección Derecha (Formulario) -->
    <div class="form-section">
      
      <div class="header-brand">
        <h2>Hotel Costa Azul</h2>
      </div>

      <div class="welcome-text">
        <h1>Bienvenido de nuevo</h1>
        <p>Ingresa con tu cuenta para continuar.</p>
      </div>

      <!-- Alertas de error si las credenciales fallan -->
      <?php if ($error === 'credenciales'): ?>
        <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          Correo o contraseña incorrectos. Por favor, intenta de nuevo.
        </div>
      <?php elseif ($error === 'registro'): ?>
        <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          Ocurrió un error al registrar la cuenta. El correo podría estar en uso.
        </div>
      <?php endif; ?>

      <!-- Formulario conectado al controlador de autenticación -->
      <form id="loginForm" class="login-form" method="POST" action="../controladores/auth_controller.php">
        
        <!-- Campo Operación Oculto -->
        <input type="hidden" name="operacion" value="login">

        <!-- Campo Correo / Usuario -->
        <div class="input-group">
          <label for="username">Correo electrónico / Usuario</label>
          <div class="input-wrapper">
            <i class="fa-regular fa-user icon-left"></i>
            <input type="text" id="username" name="correo" placeholder="Ej. correo@hotelcostaazul.com" required>
          </div>
        </div>

        <!-- Campo Contraseña -->
        <div class="input-group">
          <label for="password">Contraseña</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" id="password" name="password" required>
            <i class="fa-regular fa-eye icon-toggle-password" id="togglePassword"></i>
          </div>
        </div>

        <!-- Botón Iniciar Sesión -->
        <button type="submit" class="btn-submit">Iniciar sesión</button>

      </form>

      <!-- Pie del Formulario -->
      <div class="form-footer">
        ¿Eres personal nuevo o huésped? <a href="registro.php">Crear cuenta</a>
      </div>

    </div>

  </div>

  <!-- Script para alternar visibilidad de contraseña (opcional) -->
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