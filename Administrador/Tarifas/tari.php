<?php
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: /Hotel-Costa-Azul/personal_login/login_personal.php");
    exit;
}

require_once '../../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Procesar el formulario cuando se envía una nueva tarifa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_tarifa') {
    $tipo_habitacion = trim($_POST['tipo'] ?? '');
    $precio = $_POST['precio'] ?? 0;
    $vigente_desde = $_POST['vigente_desde'] ?? '';

    if (!empty($tipo_habitacion) && !empty($precio) && !empty($vigente_desde)) {
        try {
            // 1. Marcar la tarifa actual del mismo tipo como histórica (vigente = 0)
            $stmt_actualizar = $conexion->prepare("UPDATE tarifa SET vigente = 0, fecha_fin = ? WHERE tipo_habitacion = ? AND vigente = 1");
            $stmt_actualizar->execute([$vigente_desde, $tipo_habitacion]);

            // 2. Insertar la nueva tarifa como vigente (vigente = 1)
            $stmt_insertar = $conexion->prepare("INSERT INTO tarifa (tipo_habitacion, precio_noche, vigente, fecha_inicio, fecha_fin) VALUES (?, ?, 1, ?, NULL)");
            if ($stmt_insertar->execute([$tipo_habitacion, $precio, $vigente_desde])) {
                $mensaje_exito = "¡Nueva tarifa guardada con éxito!";
            }
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) { $conexion->rollBack(); }
            error_log('[Hotel Costa Azul] tari.php: ' . $e->getMessage());
            $mensaje_error = "No se pudo guardar la tarifa. Intenta de nuevo.";
        }
    } else {
        $mensaje_error = "Por favor completa todos los campos del formulario.";
    }
}

// Consultar todas las tarifas ordenadas por fecha
try {
    $stmt_tarifas = $conexion->query("SELECT tipo_habitacion AS tipo, precio_noche AS precio, fecha_inicio AS desde, fecha_fin AS hasta, 
        CASE WHEN vigente = 1 THEN 'Vigente' ELSE 'Histórica' END AS estado 
        FROM tarifa ORDER BY fecha_inicio DESC");
    $lista_tarifas = $stmt_tarifas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al guardar la tarifa.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Costa Azul - Tarifas</title>
    <link rel="stylesheet" href="tari.css">
</head>
<body>

    <!-- Encabezado / Navbar -->
    <header class="navbar">
        <div class="logo">Hotel Costa Azul</div>
        <nav class="nav-links">
    <a href="../Reportes/repo.php">Reportes</a>
    <a href="tari.php" class="active">Tarifas</a>
    <a href="../Politicas/poli.php">Políticas</a>
    <a href="../Usuarios/usuarios.php">Usuarios</a>
</nav>
        <div class="user-profile">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
            <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'] ?? 'Invitado')); ?></span>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="main-layout">
        
        <!-- Panel Izquierdo: Tabla de Tarifas -->
        <main class="content-panel">
            <h1 class="page-title">Tarifas</h1>
            <p class="page-subtitle">Hoy · <?php echo date('d M Y'); ?> · precio por noche según el tipo de habitación</p>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
            <?php endif; ?>
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
            <?php endif; ?>

            <div class="table-card">
                <table class="rates-table">
                    <thead>
                        <tr>
                            <th>TIPO</th>
                            <th>PRECIO POR NOCHE</th>
                            <th>DESDE</th>
                            <th>HASTA</th>
                            <th>ESTADO</th>
                        </tr>
                    </thead>
                    <tbody id="ratesTableBody">
                        <?php if (count($lista_tarifas) > 0): ?>
                            <?php foreach ($lista_tarifas as $tarifa): ?>
                                <tr class="<?php echo ($tarifa['estado'] === 'Vigente') ? 'row-highlight' : ''; ?>">
                                    <td>
                                        <?php if ($tarifa['estado'] === 'Vigente'): ?>
                                            <strong><?php echo htmlspecialchars($tarifa['tipo']); ?></strong>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($tarifa['tipo']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($tarifa['precio'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($tarifa['desde']))); ?></td>
                                    <td><?php echo $tarifa['hasta'] ? htmlspecialchars(date('d M Y', strtotime($tarifa['hasta']))) : '—'; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($tarifa['estado'] === 'Vigente') ? 'badge-vigente' : 'badge-historica'; ?>">
                                            <?php echo htmlspecialchars($tarifa['estado']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No hay tarifas registradas todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p class="table-footer-note">
                    Las tarifas históricas se conservan porque las reservaciones hechas con ellas siguen usando su precio original.
                </p>
            </div>
        </main>

        <!-- Panel Derecho: Formulario Nueva Tarifa -->
        <aside class="form-panel">
            <h2>Nueva tarifa</h2>
            <p class="form-subtitle">Al guardar, la tarifa actual del mismo tipo pasa a histórica.</p>

            <form id="rateForm" method="POST" action="">
                <input type="hidden" name="accion" value="guardar_tarifa">

                <div class="form-group">
                    <label for="roomType">Tipo de habitación</label>
                    <select id="roomType" name="tipo" required>
                        <option value="Sencilla">Sencilla</option>
                        <option value="Doble">Doble</option>
                        <!-- Puedes agregar más tipos según tu BD (ej. Suite) -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="roomPrice">Precio por noche</label>
                    <input type="number" id="roomPrice" name="precio" step="0.01" placeholder="$ 0.00" required>
                </div>

                <div class="form-group">
                    <label for="startDate">Vigente desde</label>
                    <input type="date" id="startDate" name="vigente_desde" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <button type="submit" class="btn-submit">Guardar nueva tarifa</button>
            </form>

            <p class="disclaimer-text">
                Una tarifa guardada no se edita: para cambiar un precio se crea una nueva. Las reservaciones existentes conservan su precio.
            </p>
        </aside>

    </div>

</body>
</html>