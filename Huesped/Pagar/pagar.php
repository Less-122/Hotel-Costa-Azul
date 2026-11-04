<?php
// HOTEL_COSTA/Huesped/Pagar/pagar.php
session_start();
require_once '../../config/conexion.php';

// Validar que el usuario haya iniciado sesión como Huésped
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Huesped') {
    header("Location: ../Login_regis/index.php?error=acceso_denegado");
    exit();
}

$mensaje_exito = '';
$mensaje_error = '';

// Código de reservación buscado (por defecto toma uno de prueba de tu SQL, ej. AX7B9)
$codigo_buscado = trim($_GET['codigo'] ?? 'AX7B9');
$reservacion = null;
$total_pagado = 0;
$saldo_pendiente = 0;
$costo_total = 0;
$noches = 1;

// 1. Procesar el pago cuando se envía el formulario de la tarjeta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'procesar_pago_linea') {
    $codigo_res = trim($_POST['codigo_reservacion'] ?? '');
    $tipo_pago = $_POST['tipo_pago'] ?? 'Anticipo';
    $monto = floatval($_POST['monto'] ?? 0);

    if (!empty($codigo_res) && $monto > 0) {
        try {
            // Buscar la reservación por número de confirmación o ID usando la tabla en singular
            $stmt_res = $conexion->prepare("SELECT id_reservacion FROM reservacion WHERE numero_confirmacion = ? OR id_reservacion = ?");
            $stmt_res->execute([$codigo_res, $codigo_res]);
            $res_data = $stmt_res->fetch(PDO::FETCH_ASSOC);

            if ($res_data) {
                $id_reservacion = $res_data['id_reservacion'];

                // Insertar el pago en la tabla PAGO respetando tu esquema exacto
                $stmt_insert = $conexion->prepare("
                    INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista) 
                    VALUES (?, NOW(), ?, 'Pasarela en línea', ?, NULL)
                ");
                
                if ($stmt_insert->execute([$monto, $tipo_pago, $id_reservacion])) {
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

// 2. Consultar datos reales de la reservación, su tarifa y sus pagos previos
try {
    $stmt_busqueda = $conexion->prepare("
        SELECT r.*, u.nombre AS nombre_huesped, h.tipo_habitacion, t.precio_noche 
        FROM reservacion r 
        LEFT JOIN usuario u ON r.id_huesped = u.id_usuario 
        LEFT JOIN habitacion h ON r.numero_habitacion = h.numero_habitacion 
        LEFT JOIN tarifa t ON r.id_tarifa = t.id_tarifa
        WHERE r.numero_confirmacion = ? OR r.id_reservacion = ?
    ");
    $stmt_busqueda->execute([$codigo_buscado, $codigo_buscado]);
    $reservacion = $stmt_busqueda->fetch(PDO::FETCH_ASSOC);

    if ($reservacion) {
        $id_res = $reservacion['id_reservacion'];
        
        // Calcular total dinámicamente (noches × precio por noche de la tarifa histórica)
        $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / (60 * 60 * 24));
        $precio_noche = floatval($reservacion['precio_noche'] ?? 850.00);
        $costo_total = $noches * $precio_noche;

        // Consultar suma de pagos previos asociados a esta reserva en la tabla pago
        $stmt_pagos = $conexion->prepare("SELECT SUM(monto) AS total_pagado FROM pago WHERE id_reservacion = ?");
        $stmt_pagos->execute([$id_res]);
        $res_pagos = $stmt_pagos->fetch(PDO::FETCH_ASSOC);
        $total_pagado = floatval($res_pagos['total_pagado'] ?? 0);

        // Saldo pendiente calculado
        $saldo_pendiente = max(0, $costo_total - $total_pagado);
    }
} catch (PDOException $e) {
    $reservacion = null;
}

$nombre_cliente = $reservacion ? trim($reservacion['nombre_huesped']) : $_SESSION['nombre'];
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
      <a href="../Reserva/Reser.php">Reservar</a>
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
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo $mensaje_error; ?></div>
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
              <?php echo date('d M', strtotime($reservacion['fecha_entrada'])); ?> → <?php echo date('d M Y', strtotime($reservacion['fecha_salida'])); ?> · Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion']); ?> (<?php echo htmlspecialchars($reservacion['tipo_habitacion']); ?>)
          </div>
          
          <div class="res-financials">
            <span class="fin-item">Total <b>$<?php echo number_format($costo_total, 2); ?></b></span>
            <span class="fin-dot">·</span>
            <span class="fin-item">Pagado <b>$<?php echo number_format($total_pagado, 2); ?></b></span>
          </div>

          <div class="balance-row">
            <span class="balance-label">Saldo pendiente</span>
            <span class="balance-amount" id="displayPendingBalance">$<?php echo number_format($saldo_pendiente, 2); ?></span>
          </div>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 15px;">No se encontró ninguna reservación con ese código. Verifica que sea correcto.</p>
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

          <!-- Monto a abonar -->
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

          <a href="../Reserva/Reser.php" class="back-link">← Volver</a>

        </form>
      </div>
    </aside>

  </main>

  <!-- Script para actualizar montos dinámicamente -->
  <script>
    const amountInput = document.getElementById('amountInput');
    const summaryTotalPay = document.getElementById('summaryTotalPay');
    const btnPayAmountText = document.getElementById('btnPayAmountText');

    if (amountInput) {
      amountInput.addEventListener('input', function() {
        const val = parseFloat(this.value) || 0;
        const formatted = '$' + val.toFixed(2);
        summaryTotalPay.textContent = formatted;
        btnPayAmountText.textContent = formatted;
      });
    }
  </script>
</body>
</html>