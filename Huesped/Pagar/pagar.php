<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Huesped') {
    header("Location: ../Login_regis/index.php?error=acceso_denegado");
    exit();
}

$mensaje_exito = '';
$mensaje_error = '';
$codigo_buscado = trim($_GET['codigo'] ?? 'AX7B9');
$reservacion = null;
$total_pagado = 0;
$saldo_pendiente = 0;
$costo_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'procesar_pago_linea') {
    $codigo_res = trim($_POST['codigo_reservacion'] ?? '');
    $tipo_pago = $_POST['tipo_pago'] ?? 'Anticipo';
    $monto = floatval($_POST['monto'] ?? 0);

    if (!empty($codigo_res) && $monto > 0) {
        try {
            $stmt_res = $conexion->prepare("SELECT id_reservacion FROM reservacion WHERE numero_confirmacion = ? OR id_reservacion = ?");
            $stmt_res->execute([$codigo_res, $codigo_res]);
            $res_data = $stmt_res->fetch(PDO::FETCH_ASSOC);

            if ($res_data) {
                $id_reservacion = $res_data['id_reservacion'];
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
        $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / (60 * 60 * 24));
        $precio_noche = floatval($reservacion['precio_noche'] ?? 850.00);
        $costo_total = $noches * $precio_noche;

        $stmt_pagos = $conexion->prepare("SELECT SUM(monto) AS total_pagado FROM pago WHERE id_reservacion = ?");
        $stmt_pagos->execute([$id_res]);
        $res_pagos = $stmt_pagos->fetch(PDO::FETCH_ASSOC);
        $total_pagado = floatval($res_pagos['total_pagado'] ?? 0);

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="pagar.css">
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="pagar.php" class="active">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
  </header>

  <main class="main-container">
    <section class="left-section">
      <h1>Pagar mi reservación</h1>
      <p class="subtitle">Abona un anticipo o liquida tu saldo en línea.</p>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label for="reservationCode">Código de reservación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket"></i>
            <input type="text" id="reservationCode" name="codigo" value="<?php echo htmlspecialchars($codigo_buscado); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($reservacion): ?>
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
            <span class="balance-amount">$<?php echo number_format($saldo_pendiente, 2); ?></span>
          </div>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 15px;">No se encontró ninguna reservación con ese código.</p>
      <?php endif; ?>
    </section>

    <aside class="right-section">
      <div class="card-box">
        <h2>Datos de la tarjeta</h2>
        <form id="paymentForm" method="POST" action="">
          <input type="hidden" name="accion" value="procesar_pago_linea">
          <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">
          <input type="hidden" name="tipo_pago" value="Anticipo">
          
          <div class="input-group">
            <label>Nombre en la tarjeta</label>
            <div class="input-field"><input type="text" name="cardName" value="<?php echo htmlspecialchars($nombre_cliente); ?>" required></div>
          </div>
          <div class="input-group">
            <label>Número de tarjeta</label>
            <div class="input-field"><input type="text" placeholder="•••• •••• •••• ••••" required></div>
          </div>
          <div class="form-row">
            <div class="input-group"><label>Vence</label><div class="input-field"><input type="text" placeholder="MM / AA" required></div></div>
            <div class="input-group"><label>CVV</label><div class="input-field"><input type="password" placeholder="•••" maxlength="4" required></div></div>
          </div>
          <div class="input-group" style="margin-top: 15px;">
            <label>Monto a abonar</label>
            <div class="input-field"><input type="number" step="0.01" id="amountInput" name="monto" value="<?php echo $saldo_pendiente > 0 ? $saldo_pendiente : 500; ?>" required></div>
          </div>
          <button type="submit" class="btn-pay-submit"><i class="fa-solid fa-lock"></i> Pagar</button>
        </form>
      </div>
    </aside>
  </main>
</body>
</html>