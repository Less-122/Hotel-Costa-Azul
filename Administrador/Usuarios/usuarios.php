<?php
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /Hotel-Costa-Azul/personal_login/login_personal.php");
    exit;
}

require_once '../../config/conexion.php';

$roles  = ['Admin', 'Recepcionista', 'Camarista', 'Huesped'];
$avisos = [
    'creado'    => 'Usuario registrado correctamente.',
    'editado'   => 'Usuario actualizado correctamente.',
    'eliminado' => 'Usuario eliminado correctamente.',
];

$mensaje_exito = $avisos[$_GET['ok'] ?? ''] ?? '';
$mensaje_error = '';

// Datos del formulario: vacío = nuevo usuario, con id = editando
$form = ['id_usuario' => 0, 'nombre' => '', 'contacto' => '', 'correo' => '', 'rol' => 'Recepcionista'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_usuario'] ?? 0);

    // ELIMINAR
    if (($_POST['accion'] ?? '') === 'eliminar') {
        if ($id === (int) $_SESSION['id_usuario']) {
            $mensaje_error = "No puedes eliminar tu propia cuenta.";
        } else {
            try {
                $conexion->prepare("DELETE FROM usuario WHERE id_usuario = ?")->execute([$id]);
                header("Location: usuarios.php?ok=eliminado");
                exit;
            } catch (PDOException $e) {
                $mensaje_error = "No se puede eliminar: el usuario tiene reservaciones o pagos asociados.";
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

        if ($form['nombre'] === '' || $form['correo'] === '' || ($id === 0 && $password === '')) {
            $mensaje_error = "Completa todos los campos obligatorios.";
        } elseif (!preg_match('/^\d{10}$/', $form['contacto'])) {
            $mensaje_error = "El contacto debe tener 10 dígitos.";
        } elseif (!in_array($form['rol'], $roles, true)) {
            $mensaje_error = "Rol no válido.";
        } else {
            $datos = [
                'nombre'   => $form['nombre'],
                'contacto' => $form['contacto'],
                'correo'   => $form['correo'],
                'rol'      => $form['rol'],
            ];
            if ($password !== '') {
                $datos['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            try {
                if ($id) {
                    $sql = "UPDATE usuario SET nombre = :nombre, contacto = :contacto, correo = :correo, rol = :rol"
                         . ($password !== '' ? ", password = :password" : "")
                         . " WHERE id_usuario = :id";
                    $datos['id'] = $id;
                } else {
                    $sql = "INSERT INTO usuario (nombre, contacto, correo, password, rol)
                            VALUES (:nombre, :contacto, :correo, :password, :rol)";
                }
                $conexion->prepare($sql)->execute($datos);

                header("Location: usuarios.php?ok=" . ($id ? 'editado' : 'creado'));
                exit;
            } catch (PDOException $e) {
                $mensaje_error = ($e->getCode() == 23000)
                    ? "Ese correo ya está registrado."
                    : "No se pudo guardar el usuario.";
            }
        }
    }
}

// Cargar usuario a editar (solo si no venimos de un POST con error)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $stmt = $conexion->prepare("SELECT id_usuario, nombre, contacto, correo, rol FROM usuario WHERE id_usuario = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC) ?: $form;
}

$editando = $form['id_usuario'] > 0;

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
        <div class="user-profile">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'])); ?></span>
        </div>
    </header>

    <div class="main-layout">

        <main class="content-panel">
            <h1 class="page-title">Usuarios</h1>
            <p class="page-subtitle">Cuentas del personal y huéspedes registrados en el sistema</p>

            <?php if ($mensaje_exito): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error); ?></div>
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
                            <tr class="<?php echo ($u['id_usuario'] == $form['id_usuario']) ? 'row-highlight' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($u['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['contacto']); ?></td>
                                <td><?php echo htmlspecialchars($u['correo']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($u['rol']); ?>">
                                        <?php echo htmlspecialchars($u['rol']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones">
                                        <a href="usuarios.php?editar=<?php echo $u['id_usuario']; ?>" class="btn-link">Editar</a>
                                        <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                                            <form method="POST" onsubmit="return confirm('¿Eliminar a este usuario?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_usuario" value="<?php echo $u['id_usuario']; ?>">
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
                    Un usuario con reservaciones o pagos asociados no se puede eliminar.
                </p>
            </div>
        </main>

        <aside class="form-panel">
            <h2><?php echo $editando ? 'Editar usuario' : 'Nuevo usuario'; ?></h2>
            <p class="form-subtitle">
                <?php echo $editando ? 'Modifica los datos y guarda los cambios.' : 'Registra una nueva cuenta en el sistema.'; ?>
            </p>

            <form method="POST">
                <input type="hidden" name="id_usuario" value="<?php echo $form['id_usuario']; ?>">

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($form['nombre']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="contacto">Contacto (10 dígitos)</label>
                    <input type="text" id="contacto" name="contacto" maxlength="10" pattern="\d{10}" inputmode="numeric"
                           value="<?php echo htmlspecialchars($form['contacto']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($form['correo']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña<?php echo $editando ? ' (vacío para conservar la actual)' : ''; ?></label>
                    <input type="password" id="password" name="password" <?php echo $editando ? '' : 'required'; ?>>
                </div>

                <div class="form-group">
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r; ?>" <?php echo ($form['rol'] === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
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