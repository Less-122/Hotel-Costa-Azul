<?php
session_start();

// Validación de seguridad por rol: Solo recepción o administradores pueden acceder
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'recepcion' && $_SESSION['rol'] !== 'admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Código de reservación buscado (por defecto o vía GET/POST)
$codigo_buscado = trim($_REQUEST['codigo'] ?? 'AX7B9');

// Datos por defecto de la reservación y finanzas
$reservacion = null;
$historial_pagos = [];
$total_pagado = 0;
$saldo_pendiente = 0;
$tipo_pago_seleccionado = 'Anticipo';

// 1. Procesar el registro de un nuevo pago si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_pago') {
    $codigo_res = trim($_POST['codigo_reservacion'] ?? '');
    $tipo_pago = $_POST['tipo_pago'] ?? 'Anticipo';
    $monto = floatval($_POST['monto'] ?? 0);
    $registró = $_SESSION['nombre'] ?? 'Recepcionista';

    if (!empty($codigo_res) && $monto > 0) {
        try {
            // Buscamos el ID de la reservación a partir del código
            $stmt_res = $conexion->prepare("SELECT id_reservacion FROM reservaciones WHERE codigo = ? OR id_reservacion = ?");
            $stmt_res->execute([$codigo_res, $codigo_res]);
            $res_data = $stmt_res->fetch(PDO::FETCH_ASSOC);

            if ($res_data) {
                $id_reservacion = $res_data['id_reservacion'];

                // Insertamos el pago en la base de datos
                $stmt_insert = $conexion->prepare("INSERT INTO pagos (id_reservacion, tipo_pago, monto, registrado_por, fecha_pago) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt_insert->execute([$id_reservacion, $tipo_pago, $monto, $registró])) {
                    $mensaje_exito = "¡Pago registrado correctamente!";
                    $codigo_buscado = $codigo_res; // Mantenemos la búsqueda activa
                }
            } else {
                $mensaje_error = "No se encontró ninguna reservación con el código: " . htmlspecialchars($codigo_res);
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al registrar el pago: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor ingresa un monto válido.";
    }
}

try {
    // 2. Buscar información de la reservación y el huésped
    $stmt_busqueda = $conexion->prepare("
        SELECT r.*, u.nombre AS nombre_huesped, h.tipo AS tipo_habitacion, h.numero AS num_habitacion 
        FROM reservaciones r 
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario 
        LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion 
        WHERE r.codigo = ? OR r.id_reservacion = ?
    ");
    $stmt_busqueda->execute([$codigo_buscado, $codigo_buscado]);
    $reservacion = $stmt_busqueda->fetch(PDO::FETCH_ASSOC);

    if ($reservacion) {
        $id_res = $reservacion['id_reservacion'];
        $costo_total = floatval($reservacion['total'] ?? 2400.00);

        // 3. Consultar el historial de pagos de esta reservación
        $stmt_pagos = $conexion->prepare("SELECT fecha_pago, tipo_pago, monto, registrado_por FROM pagos WHERE id_reservacion = ? ORDER BY fecha_pago DESC");
        $stmt_pagos->execute([$id_res]);
        $historial_pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

        // Calcular total pagado
        foreach ($historial_pagos as $pago) {
            $total_pagado += floatval($pago['monto']);
        }

        $saldo_pendiente = max(0, $costo_total - $total_pagado);
    }
} catch (PDOException $e) {
    // Si las tablas no tienen exactamente esa estructura, evitamos que rompa la página
    $reservacion = null;
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Marta Pérez';
$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Recepcionista');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Gestión de Pagos (Recepción)</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos -->
  <link rel="stylesheet" href="pagos.css">
</head>
<body>

  <!-- Navbar Superior de Recepción -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="recep.php">Reservaciones</a>
      <a href="hab.php">Habitaciones</a>
      <a href="pagos.php" class="active">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        <span class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></span>
      </div>
    </div>
  </header>

  <!-- Contenedor Principal -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Búsqueda, Resumen de saldo y Registro de pago -->
    <section class="left-column">
      
      <div class="header-title-area">
        <h1>Pagos</h1>
        <p>Anticipos y liquidaciones</p>
      </div>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <!-- Buscador de código de reservación -->
      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label>Código de reservación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket"></i>
            <input type="text" name="codigo" value="<?php echo htmlspecialchars($codigo_buscado); ?>" required>
          </div>
        </div>
        <button type="submit" class="btn-search">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </form>

      <?php if ($reservacion): ?>
        <!-- Tarjeta de Resumen Financiero del Huésped -->
        <div class="guest-financial-card">
          <div class="guest-name-title"><?php echo htmlspecialchars($reservacion['nombre_huesped'] ?? 'Huésped General'); ?></div>
          <div class="guest-stay-info">
              <?php echo date('d M', strtotime($reservacion['fecha_entrada'] ?? 'now')); ?> → <?php echo date('d M Y', strtotime($reservacion['fecha_salida'] ?? 'now')); ?> · <?php echo htmlspecialchars($reservacion['tipo_habitacion'] ?? 'Habitación'); ?>
          </div>

          <div class="financial-rows">
            <div class="fin-row">
              <span>Total</span>
              <b>$<?php echo number_format($reservacion['total'] ?? 2400, 2); ?></b>
            </div>
            <div class="fin-row">
              <span>Pagado</span>
              <b>$<?php echo number_format($total_pagado, 2); ?></b>
            </div>
          </div>

          <div class="balance-pending-row">
            <span class="balance-title">Saldo pendiente</span>
            <span class="balance-val" id="pendingBalanceDisplay">$<?php echo number_format($saldo_pendiente, 2); ?></span>
          </div>
        </div>

        <!-- Tarjeta de Registrar Pago -->
        <div class="register-payment-box">
          <h2>Registrar pago</h2>
          
          <form method="POST" action="">
            <input type="hidden" name="accion" value="registrar_pago">
            <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">

            <div class="input-group">
              <label>Tipo de pago</label>
              <div class="payment-type-options">
                <label style="flex: 1; text-align: center; cursor: pointer;">
                    <input type="radio" name="tipo_pago" value="Anticipo" checked style="display: none;" onchange="this.parentElement.parentElement.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('active')); this.nextElementSibling.classList.add('active');">
                    <button type="button" class="type-btn active" onclick="this.previousElementSibling.click();">Anticipo</button>
                </label>
                <label style="flex: 1; text-align: center; cursor: pointer;">
                    <input type="radio" name="tipo_pago" value="Liquidación" style="display: none;" onchange="this.parentElement.parentElement.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('active')); this.nextElementSibling.classList.add('active');">
                    <button type="button" class="type-btn" onclick="this.previousElementSibling.click();">Liquidación</button>
                </label>
              </div>
            </div>

            <div class="input-group">
              <label>Monto</label>
              <div class="input-field">
                <i class="fa-regular fa-credit-card"></i>
                <input type="number" step="0.01" name="monto" id="paymentAmountInput" placeholder="0.00" value="<?php echo $saldo_pendiente > 0 ? $saldo_pendiente : ''; ?>" required>
              </div>
            </div>

            <button type="submit" class="btn-register-pay">Registrar pago</button>
          </form>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No se encontró ninguna reservación activa con el código ingresado.</p>
      <?php endif; ?>

    </section>

    <!-- Columna Derecha: Historial de pagos y Alerta de éxito -->
    <aside class="right-column">
      <div class="history-card-box">
        
        <div class="history-header">
          <h2>Historial de pagos</h2>
          <i class="fa-solid fa-lock lock-top-icon"></i>
        </div>

        <!-- Tabla del Historial -->
        <div class="history-table-wrapper">
          <table>
            <thead>
              <tr>
                <th>FECHA Y HORA</th>
                <th>TIPO</th>
                <th>MONTO</th>
                <th>REGISTRÓ</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($historial_pagos) > 0): ?>
                <?php foreach ($historial_pagos as $pago): ?>
                  <tr>
                    <td><?php echo date('d M - H:i', strtotime($pago['fecha_pago'])); ?></td>
                    <td><?php echo htmlspecialchars($pago['tipo_pago']); ?></td>
                    <td>$<?php echo number_format($pago['monto'], 2); ?></td>
                    <td><?php echo htmlspecialchars($pago['registrado_por']); ?></td>
                    <td><i class="fa-solid fa-lock" title="Pago bloqueado (seguridad)"></i></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: #666;">No hay pagos registrados para esta reservación.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Nota informativa con candado -->
        <div class="security-notice-box">
          <i class="fa-solid fa-lock"></i> Los pagos guardados no se pueden editar ni eliminar.
        </div>

      </div>
    </aside>

  </main>

  <!-- Script opcional de UI -->
  <script src="pagos.js"></script>
</body>
</html>