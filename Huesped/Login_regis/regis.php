<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

// Si ya hay sesión iniciada, mandamos a cada quien a su página
if (isset($_SESSION['id_usuario'])) {
    header("Location: " . paginaInicioRol(rolActual()));
    exit();
}

/**
 * Valida nombres reales permitiendo letras (incluyendo acentos y ñ),
 * partículas comunes en minúscula (de, del, la, etc.), guiones (García-López)
 * y apóstrofos (O'Neil / O’Neil), sin números ni espacios extra.
 */
function validarNombreFormato(string $nombre): bool {
    if (!preg_match('/^[^\s]+( [^\s]+)*$/u', $nombre)) {
        return false;
    }

    $particulasMinuscula = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'dos', 'van', 'von'];
    $palabras = explode(' ', $nombre);

    foreach ($palabras as $palabra) {
        $palabraMinuscula = mb_strtolower($palabra, 'UTF-8');
        if (in_array($palabraMinuscula, $particulasMinuscula, true)) {
            if ($palabra !== $palabraMinuscula) {
                return false;
            }
            continue;
        }

        // Normaliza apóstrofo curvo a recto para validar segmentos como O'Neil o D'Angelo
        $normalizada = str_replace('’', "'", $palabra);

        // Permite inicial con punto (ej. J.) o segmento con mayúscula inicial y minúsculas
        // Encadenado con guion (-) o apóstrofo (')
        $patronSegmento = '(?:\p{Lu}\.|\p{Lu}\p{Ll}+)';
        $patronPalabra  = '/^' . $patronSegmento . "(?:[-']" . $patronSegmento . ')*$/u';

        if (!preg_match($patronPalabra, $normalizada)) {
            return false;
        }
    }

    return true;
}

$error  = '';
$valores = ['nombre' => '', 'telefono' => '', 'correo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.';
    } else {
        $valores['nombre']   = trim($_POST['nombre'] ?? '');
        $valores['telefono'] = trim($_POST['telefono'] ?? '');
        $valores['correo']   = trim($_POST['correo'] ?? '');
        $password            = $_POST['password'] ?? '';
        $confirmar           = $_POST['confirmar'] ?? '';

        if ($valores['nombre'] === '' || $valores['telefono'] === '' || $valores['correo'] === '' || $password === '') {
            $error = 'Completa todos los campos.';
        } elseif (mb_strlen($valores['nombre']) > 100) {
            $error = 'El nombre es demasiado largo (máximo 100 caracteres).';
        } elseif (!validarNombreFormato($valores['nombre'])) {
            $error = 'Escribe un nombre válido usando letras, partículas en minúscula (de, del, la), guiones o apóstrofos (ej. María de la Cruz, García-López, O’Neil).';
        } elseif (!preg_match('/^\d{10}$/', $valores['telefono'])) {
            $error = 'El teléfono debe tener 10 dígitos.';
        } elseif (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL) || strlen($valores['correo']) > 80) {
            $error = 'Escribe un correo electrónico válido.';
        } elseif (preg_match('/\s/u', $password) || preg_match('/\s/u', $confirmar)) {
            $error = 'La contraseña no debe contener espacios.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (strlen($password) > 72) {
            // bcrypt (el algoritmo de password_hash) solo considera los primeros 72 bytes
            $error = 'La contraseña no puede pasar de 72 caracteres.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            // La contraseña se convierte en hash AQUÍ, antes de tocar la base de datos.
            // A la BD solo llega el hash; el texto original nunca se guarda.
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                // El rol se fija en el servidor: nadie puede registrarse como admin/recepción desde este formulario
                $stmt = $conexion->prepare(
                    "INSERT INTO usuario (nombre, contacto, correo, password, rol)
                     VALUES (:nombre, :contacto, :correo, :password, 'Huesped')"
                );
                $stmt->execute([
                    ':nombre'   => $valores['nombre'],
                    ':contacto' => $valores['telefono'],
                    ':correo'   => $valores['correo'],
                    ':password' => $hash,
                ]);

                header("Location: login.php?registro=ok");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Ese correo ya está registrado. Intenta iniciar sesión.';
                } else {
                    error_log('Registro de huésped: ' . $e->getMessage());
                    $error = 'No se pudo completar el registro. Inténtalo de nuevo.';
                }
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

      <!-- Alertas de error -->
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="color: #d9534f; background-color: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; font-weight: 500;">
          <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form id="registerForm" class="register-form" method="POST" action="" onsubmit="return validarContrasenas();">
        <?php echo campoCsrf(); ?>

        <!-- Nombre completo -->
        <div class="input-group">
          <label for="fullname">Nombre completo</label>
          <input type="text" id="fullname" name="nombre" maxlength="100"
                 title="Letras, partículas en minúscula (de, del, la), guiones o apóstrofos (ej. María de la Cruz, García-López, O’Neil)."
                 value="<?php echo htmlspecialchars($valores['nombre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. María de la Cruz" required>
        </div>

        <!-- Fila: Teléfono -->
        <div class="form-row">
          <div class="input-group" style="width: 100%;">
            <label for="phone">Teléfono (10 dígitos)</label>
            <input type="tel" id="phone" name="telefono" placeholder="7771234567" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($valores['telefono'], ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>
        </div>

        <!-- Correo electrónico -->
        <div class="input-group">
          <label for="email">Correo electrónico (para iniciar sesión)</label>
          <input type="email" id="email" name="correo" maxlength="80" value="<?php echo htmlspecialchars($valores['correo'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="correo@ejemplo.com" required>
        </div>

        <!-- Fila: Contraseña + Confirmar contraseña -->
        <div class="form-row">
          <div class="input-group">
            <label for="password">Contraseña</label>
            <div class="input-wrapper">
              <input type="password" id="password" name="password" minlength="8" maxlength="72" pattern="^\S+$" title="No se permiten espacios en la contraseña" autocomplete="new-password" required>
              <i class="fa-regular fa-eye icon-toggle-password" id="togglePassword"></i>
            </div>
          </div>

          <div class="input-group">
            <label for="confirmPassword">Confirmar contraseña</label>
            <div class="input-wrapper">
              <input type="password" id="confirmPassword" name="confirmar" minlength="8" maxlength="72" pattern="^\S+$" title="No se permiten espacios en la contraseña" autocomplete="new-password" required>
              <i class="fa-regular fa-eye icon-toggle-password" id="toggleConfirmPassword"></i>
            </div>
          </div>
        </div>

        <p class="form-help-text" id="passwordError" style="color: red; display: none;"></p>
        <p class="form-help-text">Mínimo 8 caracteres, sin espacios.</p>

        <!-- Botón submit -->
        <button type="submit" class="btn-submit">Crear cuenta</button>

      </form>

      <!-- Enlace para volver al login -->
      <div class="form-footer">
        ¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a>
      </div>

    </div>

  </div>

  <!-- Script de validación de contraseñas y visibilidad -->
  <script>
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const errorText = document.getElementById('passwordError');

    function mostrarErrorPassword(msg) {
      if (!errorText) return;
      errorText.textContent = msg;
      errorText.style.display = 'block';
    }

    function ocultarErrorPassword() {
      if (!errorText) return;
      errorText.textContent = '';
      errorText.style.display = 'none';
    }

    [passInput, confirmInput].forEach(campo => {
      if (!campo) return;
      campo.addEventListener('input', function () {
        if (/\s/.test(this.value)) {
          this.value = this.value.replace(/\s+/g, '');
        }
        ocultarErrorPassword();
      });
    });

    function validarContrasenas() {
      const pass = passInput ? passInput.value : '';
      const confirmPass = confirmInput ? confirmInput.value : '';

      if (/\s/.test(pass) || /\s/.test(confirmPass)) {
        mostrarErrorPassword('La contraseña no debe contener espacios.');
        return false;
      }

      if (pass !== confirmPass) {
        mostrarErrorPassword('Las contraseñas no coinciden.');
        return false;
      }

      ocultarErrorPassword();
      return true;
    }

    // Toggle para mostrar/ocultar contraseñas
    document.getElementById('togglePassword').addEventListener('click', function() {
      const p = document.getElementById('password');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
      const p = document.getElementById('confirmPassword');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>