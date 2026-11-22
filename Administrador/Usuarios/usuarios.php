<?php
require_once '../../config/sesion.php';
verificarAcceso('admin');

require_once '../../config/conexion.php';

$roles  = ['Admin', 'Recepcionista', 'Camarista', 'Huesped'];
$avisos = [
    'creado'    => 'Usuario registrado correctamente.',
    'editado'   => 'Usuario actualizado correctamente.',
    'eliminado' => 'Usuario eliminado correctamente.',
];
$mensaje_exito = $avisos[$_GET['ok'] ?? ''] ?? '';
$mensaje_error = '';

$id_sesion = (int) $_SESSION['id_usuario'];

/**
 * Valida nombres permitiendo únicamente letras (incluyendo acentos y ñ),
 * apóstrofes (' o ’) y espacios simples entre palabras (sin números, puntos ni guiones).
 */
function validarNombreFormato(string $nombre): bool {
    if ($nombre === '') {
        return false;
    }

    // Evita espacios al inicio/final o múltiples espacios consecutivos
    if (!preg_match('/^[^\s]+( [^\s]+)*$/u', $nombre)) {
        return false;
    }

    $normalizada = str_replace('’', "'", $nombre);

    // Cada palabra debe contener solo letras Unicode o apóstrofe (no solo un apóstrofe)
    return preg_match('/^[\p{L}\']+(?: [\p{L}\']+)*$/u', $normalizada) === 1
        && preg_match('/(?:^| )\'|\'(?: |$)/u', $normalizada) !== 1;
}

/**
 * Valida formato de correo electrónico estándar y sin espacios.
 */
function validarCorreoFormato(string $correo): bool {
    if ($correo === '' || preg_match('/\s/u', $correo)) {
        return false;
    }

    if (mb_strlen($correo, 'UTF-8') > 80) {
        return false;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u', $correo) === 1;
}

// Datos del formulario: vacío = nuevo usuario, con id = editando
$form = ['id_usuario' => 0, 'nombre' => '', 'contacto' => '', 'correo' => '', 'rol' => 'Recepcionista'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $mensaje_error = "Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.";
        goto render_page;
    }

    $id = (int) ($_POST['id_usuario'] ?? 0);

    // ELIMINAR
    if (($_POST['accion'] ?? '') === 'eliminar') {
        if ($id === $id_sesion) {
            $mensaje_error = "No puedes eliminar tu propia cuenta.";
        } elseif ($id <= 0) {
            $mensaje_error = "Usuario no válido.";
        } else {
            try {
                $conexion->beginTransaction();

                $st_target = $conexion->prepare("SELECT rol FROM usuario WHERE id_usuario = ? FOR UPDATE");
                $st_target->execute([$id]);
                $target_rol = $st_target->fetchColumn();

                if ($target_rol === false) {
                    $conexion->rollBack();
                    $mensaje_error = "El usuario no existe.";
                } else {
                    if (strtolower((string) $target_rol) === 'admin') {
                        $total_admins = (int) $conexion->query(
                            "SELECT COUNT(*) FROM usuario WHERE LOWER(rol) = 'admin' FOR UPDATE"
                        )->fetchColumn();
                        if ($total_admins <= 1) {
                            $conexion->rollBack();
                            $mensaje_error = "No se puede eliminar al último administrador del sistema.";
                            goto render_page;
                        }
                    }

                    $conexion->prepare("DELETE FROM usuario WHERE id_usuario = ?")->execute([$id]);
                    $conexion->commit();
                    header("Location: usuarios.php?ok=eliminado");
                    exit;
                }
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                error_log('Usuarios - eliminar: ' . $e->getMessage());
                $mensaje_error = ($e->getCode() == 23000)
                    ? "No se puede eliminar: el usuario tiene reservaciones o pagos asociados."
                    : "No se pudo eliminar el usuario.";
            }
        }
    }
    // CREAR / EDITAR
    else {
        $form = [
            'id_usuario' => $id,
            'nombre'     => trim($_POST['nombre'] ?? ''),
            'contacto'   => trim($_POST['contacto'] ?? ''),
            'correo'     => trim($_POST['correo'] ?? ''),
            'rol'        => $_POST['rol'] ?? '',
        ];
        $password = $_POST['password'] ?? '';

        $usuario_actual = null;
        if ($id > 0) {
            $st_act = $conexion->prepare("SELECT id_usuario, rol FROM usuario WHERE id_usuario = ?");
            $st_act->execute([$id]);
            $usuario_actual = $st_act->fetch(PDO::FETCH_ASSOC);
            if (!$usuario_actual) {
                $mensaje_error = "El usuario a editar no existe.";
            }
        }

        $validar_password = ($id === 0 || $password !== '');

        if ($mensaje_error !== '') {
            // Ya tiene error asignado
        } elseif ($form['nombre'] === '' || $form['correo'] === '' || ($id === 0 && $password === '')) {
            $mensaje_error = "Completa todos los campos obligatorios.";
        } elseif (mb_strlen($form['nombre'], 'UTF-8') > 100) {
            $mensaje_error = "El nombre no puede exceder los 100 caracteres.";
        } elseif (!validarNombreFormato($form['nombre'])) {
            $mensaje_error = "El nombre solo puede contener letras, acentos y apóstrofes (ej. María López, O'Neil).";
        } elseif (!preg_match('/^\d{10}$/', $form['contacto'])) {
            $mensaje_error = "El contacto debe contener exactamente 10 dígitos numéricos y sin espacios.";
        } elseif (!validarCorreoFormato($form['correo'])) {
            $mensaje_error = "Ingresa un correo electrónico válido sin espacios (máximo 80 caracteres).";
        } elseif (!in_array($form['rol'], $roles, true)) {
            $mensaje_error = "Rol no válido.";
        } elseif ($validar_password && preg_match('/\s/u', $password)) {
            $mensaje_error = "La contraseña no puede contener espacios.";
        } elseif ($validar_password && mb_strlen($password, 'UTF-8') < 8) {
            $mensaje_error = "La contraseña debe tener al menos 8 caracteres.";
        } elseif ($validar_password && strlen($password) > 72) {
            $mensaje_error = "La contraseña no puede exceder más.";
        } elseif ($id > 0 && $id === $id_sesion && $form['rol'] !== $usuario_actual['rol']) {
            $mensaje_error = "No puedes cambiar tu propio rol desde este panel.";
        } else {
            try {
                $conexion->beginTransaction();

                if ($id > 0) {
                    $st_lock = $conexion->prepare("SELECT rol FROM usuario WHERE id_usuario = ? FOR UPDATE");
                    $st_lock->execute([$id]);
                    $rol_previo = $st_lock->fetchColumn();

                    if ($rol_previo !== false && strtolower((string) $rol_previo) === 'admin' && strtolower($form['rol']) !== 'admin') {
                        $total_admins = (int) $conexion->query(
                            "SELECT COUNT(*) FROM usuario WHERE LOWER(rol) = 'admin' FOR UPDATE"
                        )->fetchColumn();
                        if ($total_admins <= 1) {
                            $conexion->rollBack();
                            $mensaje_error = "No puedes quitar el rol de administrador al último administrador del sistema.";
                            goto render_page;
                        }
                    }

                    $datos = [
                        'nombre'   => $form['nombre'],
                        'contacto' => $form['contacto'],
                        'correo'   => $form['correo'],
                        'rol'      => $form['rol'],
                        'id'       => $id,
                    ];
                    $sql = "UPDATE usuario
                            SET nombre = :nombre, contacto = :contacto, correo = :correo, rol = :rol"
                         . ($password !== '' ? ", password = :password" : "")
                         . " WHERE id_usuario = :id";
                    if ($password !== '') {
                        $datos['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $conexion->prepare($sql)->execute($datos);
                } else {
                    $datos = [
                        'nombre'   => $form['nombre'],
                        'contacto' => $form['contacto'],
                        'correo'   => $form['correo'],
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'rol'      => $form['rol'],
                    ];
                    $sql = "INSERT INTO usuario (nombre, contacto, correo, password, rol)
                            VALUES (:nombre, :contacto, :correo, :password, :rol)";
                    $conexion->prepare($sql)->execute($datos);
                }

                $conexion->commit();
                header("Location: usuarios.php?ok=" . ($id ? 'editado' : 'creado'));
                exit;
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                error_log('Usuarios - guardar: ' . $e->getMessage());
                $mensaje_error = ($e->getCode() == 23000)
                    ? "Ese correo ya está registrado."
                    : "No se pudo guardar el usuario.";
            }
        }
    }
}

render_page:

// Cargar usuario a editar (solo si no venimos de un POST con error)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $stmt = $conexion->prepare("SELECT id_usuario, nombre, contacto, correo, rol FROM usuario WHERE id_usuario = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($encontrado) {
        $form = $encontrado;
    }
}

$editando = (int) $form['id_usuario'] > 0;

$usuarios = $conexion->query(
    "SELECT id_usuario, nombre, contacto, correo, rol FROM usuario ORDER BY id_usuario DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Usuarios</title>
    <link rel="stylesheet" href="usuarios.css">
</head>
<body>

    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
            <a href="../Reportes/repo.php">Reportes</a>
            <a href="../Tarifas/tari.php">Tarifas</a>
            <a href="../Politicas/poli.php">Políticas</a>
            <a href="usuarios.php" class="active">Usuarios</a>
        </nav>
        <div class="user-area">
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'], ENT_QUOTES, 'UTF-8')); ?></span>
            </div>
            <a href="../../config/logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="main-layout">

        <main class="content-panel">
            <h1 class="page-title">Usuarios</h1>
            <p class="page-subtitle">Cuentas del personal y huéspedes registrados en el sistema</p>

            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="table-card">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>CONTACTO</th>
                            <th>CORREO</th>
                            <th>ROL</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php $es_propio = ((int) $u['id_usuario'] === $id_sesion); ?>
                            <tr class="<?php echo ((int) $u['id_usuario'] === (int) $form['id_usuario']) ? 'row-highlight' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['contacto'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars(strtolower($u['rol']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones">
                                        <a href="usuarios.php?editar=<?php echo (int) $u['id_usuario']; ?>" class="btn-link">Editar</a>
                                        <?php if (!$es_propio): ?>
                                            <form method="POST" onsubmit="return confirm('¿Eliminar a este usuario?');">
                                                <?php echo campoCsrf(); ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
                                                <button type="submit" class="btn-link btn-danger">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$usuarios): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No hay usuarios registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p class="table-footer-note">
                    Un usuario con reservaciones o pagos asociados no se puede eliminar, ni se puede dejar al sistema sin administradores.
                </p>
            </div>
        </main>

        <aside class="form-panel">
            <h2><?php echo $editando ? 'Editar usuario' : 'Nuevo usuario'; ?></h2>
            <p class="form-subtitle">
                <?php echo $editando ? 'Modifica los datos y guarda los cambios.' : 'Registra una nueva cuenta en el sistema.'; ?>
            </p>

            <form id="userForm" method="POST" novalidate>
                <?php echo campoCsrf(); ?>
                <input type="hidden" name="id_usuario" value="<?php echo (int) $form['id_usuario']; ?>">

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100"
                           title="Solo letras, acentos y apóstrofes (ej. María López, O'Neil)."
                           value="<?php echo htmlspecialchars($form['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="contacto">Contacto (10 dígitos)</label>
                    <input type="text" id="contacto" name="contacto" maxlength="10" pattern="\d{10}" inputmode="numeric"
                           title="10 dígitos numéricos sin espacios."
                           value="<?php echo htmlspecialchars($form['contacto'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="80"
                           title="Ingresa un correo electrónico válido sin espacios."
                           value="<?php echo htmlspecialchars($form['correo'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña<?php echo $editando ? ' (mín. 8 caracteres si la cambias, máx. 72 bytes, sin espacios)' : ' (8 a 72 bytes, sin espacios)'; ?></label>
                    <input type="password" id="password" name="password" minlength="8"
                           title="Sin espacios, mínimo 8 caracteres s."
                           <?php echo $editando ? '' : 'required'; ?>>
                </div>

                <div class="form-group">
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol" required <?php echo ($editando && (int) $form['id_usuario'] === $id_sesion) ? 'disabled' : ''; ?>>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($form['rol'] === $r) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editando && (int) $form['id_usuario'] === $id_sesion): ?>
                        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($form['rol'], ENT_QUOTES, 'UTF-8'); ?>">
                        <small style="color: #64748b; display: block; margin-top: 4px;">Por seguridad no puedes modificar tu propio rol.</small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit"><?php echo $editando ? 'Guardar cambios' : 'Guardar usuario'; ?></button>
                <?php if ($editando): ?>
                    <a href="usuarios.php" class="btn-cancelar">Cancelar</a>
                <?php endif; ?>
            </form>

            <p class="disclaimer-text">
                Las contraseñas se guardan cifradas y no se pueden consultar.
            </p>
        </aside>

    </div>

    <script>
        (function () {
            const nombreInput   = document.getElementById('nombre');
            const contactoInput = document.getElementById('contacto');
            const correoInput   = document.getElementById('correo');
            const passwordInput = document.getElementById('password');
            const form          = document.getElementById('userForm');
            const esEdicion     = <?php echo $editando ? 'true' : 'false'; ?>;

            const encoder = new TextEncoder();

            function byteLength(str) {
                return encoder.encode(str).length;
            }

            // Nombre: solo letras (incluyendo acentos y ñ), apóstrofes (' o ’) y espacios
            if (nombreInput) {
                nombreInput.addEventListener('input', function () {
                    const limpio = this.value.replace(/[^\p{L}'’ ]/gu, '');
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            // Contacto: solo dígitos numéricos, sin espacios, máx 10
            if (contactoInput) {
                contactoInput.addEventListener('keydown', function (e) {
                    if (e.key === ' ') {
                        e.preventDefault();
                    }
                });
                contactoInput.addEventListener('input', function () {
                    const limpio = this.value.replace(/\D/g, '').slice(0, 10);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            // Correo: sin espacios y máx 80 caracteres
            if (correoInput) {
                correoInput.addEventListener('keydown', function (e) {
                    if (e.key === ' ') {
                        e.preventDefault();
                    }
                });
                correoInput.addEventListener('input', function () {
                    const limpio = this.value.replace(/\s+/g, '').slice(0, 80);
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            // Contraseña: sin espacios y limitada a 72 bytes
            if (passwordInput) {
                passwordInput.addEventListener('keydown', function (e) {
                    if (e.key === ' ') {
                        e.preventDefault();
                    }
                });
                passwordInput.addEventListener('input', function () {
                    let limpio = this.value.replace(/\s+/g, '');
                    while (byteLength(limpio) > 72 && limpio.length > 0) {
                        limpio = limpio.slice(0, -1);
                    }
                    if (this.value !== limpio) {
                        this.value = limpio;
                    }
                });
            }

            // Validación previa al envío
            if (form) {
                form.addEventListener('submit', function (e) {
                    const nombre   = nombreInput ? nombreInput.value.trim() : '';
                    const contacto = contactoInput ? contactoInput.value.trim() : '';
                    const correo   = correoInput ? correoInput.value.trim() : '';
                    const pass     = passwordInput ? passwordInput.value : '';

                    const nombreRegex = /^[\p{L}'’]+(?: [\p{L}'’]+)*$/u;
                    const correoRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

                    if (!nombre || nombre.length > 100 || !nombreRegex.test(nombre)) {
                        e.preventDefault();
                        alert('El nombre solo puede contener letras, acentos y apóstrofes (sin espacios dobles).');
                        nombreInput?.focus();
                        return;
                    }

                    if (!/^\d{10}$/.test(contacto)) {
                        e.preventDefault();
                        alert('El contacto debe tener exactamente 10 dígitos numéricos y sin espacios.');
                        contactoInput?.focus();
                        return;
                    }

                    if (!correo || correo.length > 80 || !correoRegex.test(correo)) {
                        e.preventDefault();
                        alert('Ingresa un correo electrónico válido sin espacios.');
                        correoInput?.focus();
                        return;
                    }

                    const requierePass = !esEdicion || pass.length > 0;
                    if (requierePass) {
                        if (/\s/.test(pass)) {
                            e.preventDefault();
                            alert('La contraseña no puede contener espacios.');
                            passwordInput?.focus();
                            return;
                        }
                        if (pass.length < 8) {
                            e.preventDefault();
                            alert('La contraseña debe tener al menos 8 caracteres.');
                            passwordInput?.focus();
                            return;
                        }
                        if (byteLength(pass) > 72) {
                            e.preventDefault();
                            alert('La contraseña no puede exceder a más.');
                            passwordInput?.focus();
                            return;
                        }
                    }
                });
            }
        })();
    </script>

</body>
</html>