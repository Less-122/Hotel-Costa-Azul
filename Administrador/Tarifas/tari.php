<?php
require_once '../../config/sesion.php';
verificarAcceso('admin');

require_once '../../config/conexion.php';

$hoy_str       = date('Y-m-d');
$mensaje_exito = isset($_GET['ok']) ? "¡Nueva tarifa guardada con éxito!" : '';
$mensaje_error = '';

$tipos_permitidos = ['Sencilla', 'Doble', 'Suite'];

$form = [
    'tipo'          => 'Sencilla',
    'precio'        => '',
    'vigente_desde' => $hoy_str,
];

// Generar token CSRF si no existe en la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar el formulario cuando se envía una nueva tarifa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_tarifa') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = "Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.";
    } else {
        $form['tipo']          = trim((string) ($_POST['tipo'] ?? 'Sencilla'));
        $form['precio']        = trim((string) ($_POST['precio'] ?? ''));
        $form['vigente_desde'] = trim((string) ($_POST['vigente_desde'] ?? ''));

        $precio_validado = filter_var($form['precio'], FILTER_VALIDATE_FLOAT);
        $dt_nueva        = DateTime::createFromFormat('!Y-m-d', $form['vigente_desde']);
        $fecha_valida    = $dt_nueva && $dt_nueva->format('Y-m-d') === $form['vigente_desde'];
        $dt_hoy          = new DateTime($hoy_str);

        if ($form['tipo'] === '' || !in_array($form['tipo'], $tipos_permitidos, true)) {
            $mensaje_error = "Selecciona un tipo de habitación válido.";
        } elseif ($precio_validado === false || !is_finite((float) $precio_validado) || $precio_validado <= 0 || $precio_validado > 999999.99) {
            $mensaje_error = "El precio debe ser un número válido mayor a 0 y hasta 999,999.99.";
        } elseif ($form['vigente_desde'] === '' || !$fecha_valida) {
            $mensaje_error = "La fecha de inicio no tiene un formato válido (AAAA-MM-DD).";
        } elseif ($dt_nueva > $dt_hoy) {
            $mensaje_error = "La fecha de inicio no puede ser futura porque la tarifa entra en vigor al guardarse.";
        } else {
            try {
                $conexion->beginTransaction();

                // Bloquear tarifa vigente actual del mismo tipo para validar coherencia de fechas
                $stmt_actual = $conexion->prepare(
                    "SELECT id_tarifa, fecha_inicio FROM tarifa WHERE tipo_habitacion = ? AND vigente = 1 ORDER BY id_tarifa DESC LIMIT 1 FOR UPDATE"
                );
                $stmt_actual->execute([$form['tipo']]);
                $tarifa_actual = $stmt_actual->fetch(PDO::FETCH_ASSOC);

                if ($tarifa_actual && !empty($tarifa_actual['fecha_inicio'])) {
                    $dt_inicio_actual = DateTime::createFromFormat('!Y-m-d', substr((string) $tarifa_actual['fecha_inicio'], 0, 10));
                    if ($dt_inicio_actual && $dt_nueva < $dt_inicio_actual) {
                        $conexion->rollBack();
                        $mensaje_error = "La fecha de vigencia (" . date('d M Y', $dt_nueva->getTimestamp()) . ") no puede ser anterior al inicio de la tarifa actual (" . date('d M Y', $dt_inicio_actual->getTimestamp()) . ").";
                        goto render_tari;
                    }
                }

                // 1. Marcar la tarifa actual del mismo tipo como histórica (vigente = 0)
                $stmt_actualizar = $conexion->prepare(
                    "UPDATE tarifa SET vigente = 0, fecha_fin = ? WHERE tipo_habitacion = ? AND vigente = 1"
                );
                $stmt_actualizar->execute([$form['vigente_desde'], $form['tipo']]);

                // 2. Insertar la nueva tarifa como vigente (vigente = 1)
                $stmt_insertar = $conexion->prepare(
                    "INSERT INTO tarifa (tipo_habitacion, precio_noche, vigente, fecha_inicio, fecha_fin) VALUES (?, ?, 1, ?, NULL)"
                );
                $stmt_insertar->execute([$form['tipo'], round((float) $precio_validado, 2), $form['vigente_desde']]);

                $conexion->commit();
                header("Location: tari.php?ok=1");
                exit;
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                error_log("Error en transacción al guardar tarifa: " . $e->getMessage());
                $mensaje_error = "Ocurrió un error al procesar la solicitud en la base de datos. Por favor, inténtalo más tarde.";
            }
        }
    }
}

render_tari:

$lista_tarifas = [];
try {
    $stmt_tarifas = $conexion->query(
        "SELECT tipo_habitacion AS tipo, precio_noche AS precio, fecha_inicio AS desde, fecha_fin AS hasta,
                CASE WHEN vigente = 1 THEN 'Vigente' ELSE 'Histórica' END AS estado
         FROM tarifa
         ORDER BY fecha_inicio DESC, id_tarifa DESC"
    );
    $lista_tarifas = $stmt_tarifas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al consultar tarifas: " . $e->getMessage());
    if ($mensaje_error === '') {
        $mensaje_error = "No se pudieron cargar las tarifas registradas.";
    }
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
        <div class="user-area">
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'], ENT_QUOTES, 'UTF-8')); ?></span>
            </div>
            <a href="../../config/logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="main-layout">

        <!-- Panel Izquierdo: Tabla de Tarifas -->
        <main class="content-panel">
            <h1 class="page-title">Tarifas</h1>
            <p class="page-subtitle">Hoy · <?php echo htmlspecialchars(date('d M Y'), ENT_QUOTES, 'UTF-8'); ?> · precio por noche según el tipo de habitación</p>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
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
                                            <strong><?php echo htmlspecialchars($tarifa['tipo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($tarifa['tipo'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format((float) $tarifa['precio'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($tarifa['desde'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $tarifa['hasta'] ? htmlspecialchars(date('d M Y', strtotime($tarifa['hasta'])), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                    <td>
                                        <span class="badge <?php echo ($tarifa['estado'] === 'Vigente') ? 'badge-vigente' : 'badge-historica'; ?>">
                                            <?php echo htmlspecialchars($tarifa['estado'], ENT_QUOTES, 'UTF-8'); ?>
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="roomType">Tipo de habitación</label>
                    <select id="roomType" name="tipo" required>
                        <?php foreach ($tipos_permitidos as $tp): ?>
                            <option value="<?php echo htmlspecialchars($tp, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($form['tipo'] === $tp) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tp, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="roomPrice">Precio por noche</label>
                    <input type="number" id="roomPrice" name="precio" step="0.01" min="0.01" max="999999.99" value="<?php echo htmlspecialchars($form['precio'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="$ 0.00" required>
                </div>

                <div class="form-group">
                    <label for="startDate">Vigente desde</label>
                    <input type="date" id="startDate" name="vigente_desde" max="<?php echo htmlspecialchars($hoy_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($form['vigente_desde'], ENT_QUOTES, 'UTF-8'); ?>" required>
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