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
 * Valida que el nombre contenga solo letras (incluyendo acentos y ñ) y puntos válidos,iniciando 
 * con una letra en mayúscula y continuando en minúsculas.
 */
function validarNombreFormato(string $nombre): bool {
    // Evitar espacios múltiples, tabs o saltos de línea
    if (!preg_match('/^[^\s]+( [^\s]+)*$/u', $nombre)) {
        return false;
    }

    $palabras = explode(' ', $nombre);
    foreach ($palabras as $palabra) {
        // Palabra normal: Mayúscula inicial seguida de minúsculas (ej. "María", "López")
        // o abreviatura con punto al final: Mayúscula + minúsculas opcionales + "." (ej. "Ma.", "J.")
        if (!preg_match('/^\p{Lu}\p{Ll}+\.?$/u', $palabra) && !preg_match('/^\p{Lu}\.$/u', $palabra)) {
            return false;
        }
    }

    return true;
}

// Datos del formulario: vacío = nuevo usuario, con id = editando
$form = ['id_usuario' => 0, 'nombre' => '', 'contacto' => '', 'correo' => '', 'rol' => 'Recepcionista'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        if ($mensaje_error !== '') {
            // Ya tiene error asignado
        } elseif ($form['nombre'] === '' || $form['correo'] === '' || ($id === 0 && $password === '')) {
            $mensaje_error = "Completa todos los campos obligatorios.";
        } elseif (mb_strlen($form['nombre']) > 100) {
            $mensaje_error = "El nombre no puede exceder los 100 caracteres.";
        } elseif (!validarNombreFormato($form['nombre'])) {
            $mensaje_error = "El nombre solo puede contener letras y puntos (sin números), iniciando cada palabra con mayúscula y siguiendo con minúsculas (ej. Ana Ma. López).";
        } elseif (!preg_match('/^\d{10}$/', $form['contacto'])) {
            $mensaje_error = "El contacto debe tener 10 dígitos.";
        } elseif (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['correo']) > 80) {
            $mensaje_error = "Ingresa un correo electrónico válido (máximo 80 caracteres).";
        } elseif (!in_array($form['rol'], $roles, true)) {
            $mensaje_error = "Rol no válido.";
        } elseif (($id === 0 || $password !== '') && mb_strlen($password) < 8) {
            $mensaje_error = "La contraseña debe tener al menos 8 caracteres.";
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

            <form method="POST">
                <input type="hidden" name="id_usuario" value="<?php echo (int) $form['id_usuario']; ?>">

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100"
                           title="Solo letras y puntos (sin números). Cada palabra debe iniciar con mayúscula y seguir en minúsculas."
                           value="<?php echo htmlspecialchars($form['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="contacto">Contacto (10 dígitos)</label>
                    <input type="text" id="contacto" name="contacto" maxlength="10" pattern="\d{10}" inputmode="numeric"
                           value="<?php echo htmlspecialchars($form['contacto'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" maxlength="80" value="<?php echo htmlspecialchars($form['correo'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña<?php echo $editando ? ' (mín. 8 caracteres si la cambias)' : ' (mín. 8 caracteres)'; ?></label>
                    <input type="password" id="password" name="password" minlength="8" <?php echo $editando ? '' : 'required'; ?>>
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

</body>
</html>