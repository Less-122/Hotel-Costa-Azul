<?php
session_start();

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Código de reservación buscado
$codigo_buscado = trim($_REQUEST['codigo'] ?? 'AX7B9');
$reservacion = null;
$total_pagado = 0;
$saldo_pendiente = 0;

// 1. Procesar el pago cuando se envía el formulario de la tarjeta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'procesar_pago_linea') {
    $codigo_res = trim($_POST['codigo_reservacion'] ?? '');
    $tipo_pago = $_POST['tipo_pago'] ?? 'Anticipo';
    $monto = floatval($_POST['monto'] ?? 0);
    $nombre_tarjeta = trim($_POST['cardName'] ?? '');

    if (!empty($codigo_res) && $monto > 0) {
        try {
            // Buscamos la reservación
            $stmt_res = $conexion->prepare("SELECT id_reservacion, total FROM reservaciones WHERE codigo = ? OR id_reservacion = ?");
            $stmt_res->execute([$codigo_res, $codigo_res]);
            $res_data = $stmt_res->fetch(PDO::FETCH_ASSOC);

            if ($res_data) {
                $id_reservacion = $res_data['id_reservacion'];

                // Insertamos el pago en la base de datos con canal 'En línea'
                $stmt_insert = $conexion->prepare("
                    INSERT INTO pagos (id_reservacion, tipo_pago, monto, canal, registrado_por, fecha_pago) 
                    VALUES (?, ?, ?, 'En línea', ?, NOW())
                ");
                
                $registrado_por = $_SESSION['nombre'] ?? 'Huésped (web)';
                if ($stmt_insert->execute([$id_reservacion, $tipo_pago, $monto, $registrado_por])) {
                    $mensaje_exito = "¡Pago procesado con éxito por $" . number_format($monto, 2) . "!";
                    $codigo_buscado = $codigo_res;
                }
            } else {
                $mensaje_error = "No se encontró la reservación especificada.";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al procesar el pago: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "El monto a pagar debe ser mayor a 0.";
    }
}

// 2. Consultar datos de la reservación y sus pagos previos
try {
    $stmt_busqueda = $conexion->prepare("
        SELECT r.*, u.nombre AS nombre_huesped, u.apellido AS apellido_huesped, h.tipo AS tipo_habitacion 
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

        // Consultar suma de pagos
        $stmt_pagos = $conexion->prepare("SELECT SUM(monto) AS total_pagado FROM pagos WHERE id_reservacion = ?");
        $stmt_pagos->execute([$id_res]);
        $res_pagos = $stmt_pagos->fetch(PDO::FETCH_ASSOC);
        $total_pagado = floatval($res_pagos['total_pagado'] ?? 0);

        $saldo_pendiente = max(0, $costo_total - $total_pagado);
    }
} catch (PDOException $e) {
    $reservacion = null;
}

$nombre_cliente = $reservacion ? trim(($reservacion['nombre_huesped'] ?? '') . ' ' . ($reservacion['apellido_huesped'] ?? '')) : 'Ana López Torres';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Pagar mi reservación</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos de pago -->
  <link rel="stylesheet" href="pagar.css">
</head>
<body>

  <!-- Navbar Superior -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../vistas/huesped/catalogo.php">Reservar</a>
      <a href="pagar.php" class="active">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
  </header>

  <!-- Contenedor Principal (Dos columnas) -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Pagar mi reservación -->
    <section class="left-section">
      <h1>Pagar mi reservación</h1>
      <p class="subtitle">Abona un anticipo o liquida tu saldo en línea.</p>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <!-- Código de reservación y buscador -->
      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label for="reservationCode">Código de reservación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket"></i>
            <input type="text" id="reservationCode" name="codigo" value="<?php echo htmlspecialchars($codigo_buscado); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </form>

      <?php if ($reservacion): ?>
        <!-- Tarjeta de Información de la Reserva -->
        <div class="reservation-summary-card">
          <div class="res-guest-name"><?php echo htmlspecialchars($nombre_cliente); ?></div>
          <div class="res-info-details">
              <?php echo date('d M', strtotime($reservacion['fecha_entrada'])); ?> → <?php echo date('d M Y', strtotime($reservacion['fecha_salida'])); ?> · <?php echo htmlspecialchars($reservacion['tipo_habitacion'] ?? 'Sencilla'); ?>
          </div>
          
          <div class="res-financials">
            <span class="fin-item">Total <b>$<?php echo number_format($reservacion['total'] ?? 2400, 2); ?></b></span>
            <span class="fin-dot">·</span>
            <span class="fin-item">Pagado <b>$<?php echo number_format($total_pagado, 2); ?></b></span>
          </div>

          <div class="balance-row">
            <span class="balance-label">Saldo pendiente</span>
            <span class="balance-amount" id="displayPendingBalance">$<?php echo number_format($saldo_pendiente, 2); ?></span>
          </div>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 15px;">No se encontró ninguna reservación con ese código.</p>
      <?php endif; ?>

    </section>

    <!-- Columna Derecha: Datos de la tarjeta -->
    <aside class="right-section">
      <div class="card-box">
        <h2>Datos de la tarjeta</h2>

        <form id="paymentForm" method="POST" action="">
          <input type="hidden" name="accion" value="procesar_pago_linea">
          <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">
          <input type="hidden" name="tipo_pago" id="inputTipoPago" value="Anticipo">
          
          <div class="input-group">
            <label for="cardName">Nombre en la tarjeta</label>
            <div class="input-field">
              <i class="fa-regular fa-user"></i>
              <input type="text" id="cardName" name="cardName" value="<?php echo htmlspecialchars($nombre_cliente); ?>" required>
            </div>
          </div>

          <div class="input-group">
            <label for="cardNumber">Número de tarjeta</label>
            <div class="input-field">
              <i class="fa-regular fa-credit-card"></i>
              <input type="text" id="cardNumber" placeholder="•••• •••• •••• ••••" required>
            </div>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label for="cardExp">Vence</label>
              <div class="input-field">
                <i class="fa-regular fa-calendar"></i>
                <input type="text" id="cardExp" placeholder="MM / AA" required>
              </div>
            </div>

            <div class="input-group">
              <label for="cardCvv">CVV</label>
              <div class="input-field">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="cardCvv" placeholder="•••" maxlength="4" required>
              </div>
            </div>
          </div>

          <!-- Monto a pagar -->
          <div class="input-group" style="margin-top: 15px;">
            <label for="amountInput">Monto a abonar</label>
            <div class="input-field">
              <i class="fa-regular fa-credit-card"></i>
              <input type="number" step="0.01" id="amountInput" name="monto" value="<?php echo $saldo_pendiente > 0 ? $saldo_pendiente : 500; ?>" required oninput="document.getElementById('summaryTotalPay').textContent = '$' + parseFloat(this.value || 0).toFixed(2); document.getElementById('btnPayAmountText').textContent = '$' + parseFloat(this.value || 0).toFixed(2);">
            </div>
          </div>

          <!-- Total a pagar destacado -->
          <div class="total-to-pay-box">
            <span>Total a pagar</span>
            <span class="total-pay-amount" id="summaryTotalPay">$<?php echo number_format($saldo_pendiente > 0 ? $saldo_pendiente : 500, 2); ?></span>
          </div>

          <!-- Botón Pagar -->
          <button type="submit" class="btn-pay-submit">
            <i class="fa-solid fa-lock"></i> Pagar <span id="btnPayAmountText">$<?php echo number_format($saldo_pendiente > 0 ? $saldo_pendiente : 500, 2); ?></span>
          </button>

          <p class="secure-note">Pago seguro procesado por la pasarela de pagos.</p>

          <a href="../vistas/huesped/catalogo.php" class="back-link">← Volver</a>

        </form>
      </div>
    </aside>

  </main>

  <!-- Script opcional para cambiar tipo de pago dinámicamente -->
  <script>
    const amountInput = document.getElementById('amountInput');
    const summaryTotalPay = document.getElementById('summaryTotalPay');
    const btnPayAmountText = document.getElementById('btnPayAmountText');

    amountInput.addEventListener('input', function() {
      const val = parseFloat(this.value) || 0;
      const formatted = '$' + val.toFixed(2);
      summaryTotalPay.textContent = formatted;
      btnPayAmountText.textContent = formatted;
    });
  </script>
</body>
</html>