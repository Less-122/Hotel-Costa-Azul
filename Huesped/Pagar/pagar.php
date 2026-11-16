<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = trim($_REQUEST['codigo'] ?? '');

function fecha(string $f): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Busca una reservación del huésped y calcula total, pagado y saldo. */
function buscarReservacion(PDO $conexion, string $codigo, int $id_huesped, bool $bloquear = false): ?array {
    $sql = "SELECT r.id_reservacion, r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                   r.numero_habitacion, h.tipo_habitacion, t.precio_noche,
                   DATEDIFF(r.fecha_salida, r.fecha_entrada) AS noches,
                   (SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = r.id_reservacion) AS pagado
            FROM reservacion r
            JOIN tarifa t     ON r.id_tarifa = t.id_tarifa
            JOIN habitacion h ON r.numero_habitacion = h.numero_habitacion
            WHERE r.numero_confirmacion = ? AND r.id_huesped = ?";
    if ($bloquear) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$codigo, $id_huesped]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$r) {
        return null;
    }
    $r['costo'] = max(1, (int) $r['noches']) * $r['precio_noche'];
    $r['saldo'] = max(0, round($r['costo'] - $r['pagado'], 2));
    return $r;
}

$mensaje_exito = isset($_GET['ok']) ? "Pago procesado correctamente." : '';
$mensaje_error = '';

// PAGAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = round((float) ($_POST['monto'] ?? 0), 2);

    try {
        // La transacción bloquea la reservación mientras se valida el saldo,
        // así dos pagos simultáneos no pueden sobrepasarlo.
        $conexion->beginTransaction();
        $r = buscarReservacion($conexion, $codigo, $id_huesped, true);

        if (!$r || $r['estado'] !== 'Activa') {
            $mensaje_error = "La reservación no existe, no es tuya o no está activa.";
        } elseif ($monto <= 0 || $monto > $r['saldo']) {
            $mensaje_error = "El monto debe ser mayor a 0 y no superar tu saldo pendiente ($"
                           . number_format($r['saldo'], 2) . ").";
        } else {
            $tipo = (abs($monto - $r['saldo']) < 0.005) ? 'Liquidación' : 'Anticipo';

            $conexion->prepare(
                "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                 VALUES (?, NOW(), ?, 'Pasarela en línea', ?, NULL)"
            )->execute([$monto, $tipo, $r['id_reservacion']]);

            $conexion->commit();
            header("Location: pagar.php?codigo=" . urlencode($codigo) . "&ok=1");
            exit;
        }
    } catch (PDOException $e) {
        $mensaje_error = "No se pudo procesar el pago.";
    }

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
}

// BUSCAR (solo entre las reservaciones del huésped en sesión)
$reservacion = ($codigo !== '') ? buscarReservacion($conexion, $codigo, $id_huesped) : null;
$puede_pagar = $reservacion && $reservacion['estado'] === 'Activa' && $reservacion['saldo'] > 0;
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

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo htmlspecialchars($mensaje_exito); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <form method="GET" class="search-form">
        <div class="input-group">
          <label for="reservationCode">Código de reservación</label>
          <div class="input-field">
            <i class="fa-solid fa-ticket"></i>
            <input type="text" id="reservationCode" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($reservacion): ?>
        <div class="reservation-summary-card">
          <div class="res-guest-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></div>
          <div class="res-info-details">
              <?php echo fecha($reservacion['fecha_entrada']); ?> → <?php echo fecha($reservacion['fecha_salida']); ?>
              · Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion']); ?>
              (<?php echo htmlspecialchars($reservacion['tipo_habitacion']); ?>)
          </div>
          <div class="res-financials">
            <span class="fin-item">Total <b>$<?php echo number_format($reservacion['costo'], 2); ?></b></span>
            <span class="fin-dot">·</span>
            <span class="fin-item">Pagado <b>$<?php echo number_format($reservacion['pagado'], 2); ?></b></span>
          </div>
          <div class="balance-row">
            <span class="balance-label">Saldo pendiente</span>
            <span class="balance-amount">$<?php echo number_format($reservacion['saldo'], 2); ?></span>
          </div>
        </div>
      <?php elseif ($codigo !== ''): ?>
        <p style="text-align: center; color: #666; padding: 15px;">No se encontró ninguna reservación tuya con ese código.</p>
      <?php endif; ?>
    </section>

    <aside class="right-section">
      <div class="card-box">
        <?php if ($puede_pagar): ?>
          <h2>Datos de la tarjeta</h2>
          <!-- Los datos de la tarjeta no tienen "name": no se envían ni se guardan -->
          <form method="POST" autocomplete="off">
            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>">

            <div class="input-group">
              <label for="cardName">Nombre en la tarjeta</label>
              <div class="input-field"><input type="text" id="cardName" value="<?php echo htmlspecialchars($_SESSION['nombre']); ?>" required></div>
            </div>
            <div class="input-group">
              <label for="cardNumber">Número de tarjeta</label>
              <div class="input-field"><input type="text" id="cardNumber" inputmode="numeric" maxlength="19" pattern="[0-9 ]{15,19}" placeholder="•••• •••• •••• ••••" required></div>
            </div>
            <div class="form-row">
              <div class="input-group">
                <label for="cardExp">Vence</label>
                <div class="input-field"><input type="text" id="cardExp" maxlength="7" pattern="(0[1-9]|1[0-2]) ?/ ?[0-9]{2}" placeholder="MM / AA" required></div>
              </div>
              <div class="input-group">
                <label for="cardCvv">CVV</label>
                <div class="input-field"><input type="password" id="cardCvv" inputmode="numeric" maxlength="4" pattern="[0-9]{3,4}" placeholder="•••" required></div>
              </div>
            </div>
            <div class="input-group" style="margin-top: 15px;">
              <label for="amountInput">Monto a abonar</label>
              <div class="input-field">
                <input type="number" id="amountInput" name="monto" step="0.01" min="0.01"
                       max="<?php echo $reservacion['saldo']; ?>" value="<?php echo $reservacion['saldo']; ?>" required>
              </div>
            </div>
            <button type="submit" class="btn-pay-submit"><i class="fa-solid fa-lock"></i> Pagar</button>
          </form>
        <?php elseif ($reservacion && $reservacion['estado'] !== 'Activa'): ?>
          <h2>Pago no disponible</h2>
          <p style="color: #666;">Esta reservación está <?php echo htmlspecialchars(strtolower($reservacion['estado'])); ?>.</p>
        <?php elseif ($reservacion): ?>
          <h2>Reservación liquidada</h2>
          <p style="color: #666;">No tienes saldo pendiente.</p>
        <?php else: ?>
          <h2>Datos de la tarjeta</h2>
          <p style="color: #666;">Busca tu reservación para poder pagar.</p>
        <?php endif; ?>
      </div>
    </aside>
  </main>
</body>
</html>