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

/** Valida un número de tarjeta usando el algoritmo de Luhn. */
function validarLuhn(string $digitos): bool {
    if (!preg_match('/^\d{13,19}$/', $digitos)) {
        return false;
    }
    $suma = 0;
    $alternar = false;
    for ($i = strlen($digitos) - 1; $i >= 0; $i--) {
        $n = (int) $digitos[$i];
        if ($alternar) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $suma += $n;
        $alternar = !$alternar;
    }
    return ($suma % 10) === 0;
}

/** Valida vencimiento MM/AA (o MM / AA) y verifica que no esté vencida. */
function validarVencimiento(string $exp): bool {
    if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/', trim($exp), $m)) {
        return false;
    }
    $mes = (int) $m[1];
    $anio = 2000 + (int) $m[2];

    // Último día del mes de vencimiento a las 23:59:59
    $ultimo_dia_mes = DateTime::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-%d 23:59:59', $anio, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $anio)));
    if (!$ultimo_dia_mes) {
        return false;
    }

    $ahora = new DateTime('now');
    return $ultimo_dia_mes >= $ahora;
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

$card_form = [
    'nombre_tarjeta' => $_SESSION['nombre'] ?? '',
    'numero_tarjeta' => '',
    'vencimiento'    => '',
    'cvv'            => '',
];

// PAGAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = round((float) ($_POST['monto'] ?? 0), 2);
    $card_form['nombre_tarjeta'] = trim($_POST['nombre_tarjeta'] ?? '');
    $card_form['numero_tarjeta'] = trim($_POST['numero_tarjeta'] ?? '');
    $card_form['vencimiento']    = trim($_POST['vencimiento'] ?? '');
    $card_form['cvv']            = trim($_POST['cvv'] ?? '');

    $digitos_tarjeta = preg_replace('/\D+/', '', $card_form['numero_tarjeta']);

    if ($card_form['nombre_tarjeta'] === '' || mb_strlen($card_form['nombre_tarjeta']) > 100) {
        $mensaje_error = "Ingresa un nombre válido en la tarjeta (máximo 100 caracteres).";
    } elseif (!validarLuhn($digitos_tarjeta)) {
        $mensaje_error = "El número de tarjeta no es válido.";
    } elseif (!validarVencimiento($card_form['vencimiento'])) {
        $mensaje_error = "La fecha de vencimiento no es válida o la tarjeta está vencida.";
    } elseif (!preg_match('/^\d{3,4}$/', $card_form['cvv'])) {
        $mensaje_error = "El código de seguridad (CVV) debe tener 3 o 4 dígitos.";
    } else {
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
            error_log('Pagar - procesar pago: ' . $e->getMessage());
            $mensaje_error = "No se pudo procesar el pago.";
        }

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
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
  <style>
    .navbar .nav-user {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #fff;
    }
    .navbar .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
    }
    .navbar .user-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
      font-size: 12px;
    }
    .navbar .user-info strong {
      font-size: 13px;
      font-weight: 600;
    }
    .navbar .user-info span {
      opacity: 0.8;
    }
    .navbar .btn-home {
      color: #fff;
      text-decoration: none;
      margin-left: 8px;
      font-size: 16px;
      display: inline-flex;
      align-items: center;
    }
    .navbar .btn-home:hover {
      opacity: 0.8;
    }
  </style>
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="pagar.php" class="active">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
    <div class="nav-user">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Huésped', ENT_QUOTES, 'UTF-8'); ?></strong>
        <span>Huésped</span>
      </div>
      <a href="../../Principal/index.php" class="btn-home" title="Volver al inicio">
        <i class="fa-solid fa-house"></i>
      </a>
    </div>
  </header>

  <main class="main-container">
    <section class="left-section">
      <h1>Pagar mi reservación</h1>
      <p class="subtitle">Abona un anticipo o liquida tu saldo en línea.</p>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500;"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="GET" class="search-form">
        <div class="input-group">
          <label for="reservationCode">Código de reservación</label>
          <div class="input-field">
            <i class="fa-solid fa-ticket"></i>
            <input type="text" id="reservationCode" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($reservacion): ?>
        <div class="reservation-summary-card">
          <div class="res-guest-name"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="res-info-details">
              <?php echo fecha($reservacion['fecha_entrada']); ?> → <?php echo fecha($reservacion['fecha_salida']); ?>
              · Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?>
              (<?php echo htmlspecialchars($reservacion['tipo_habitacion'], ENT_QUOTES, 'UTF-8'); ?>)
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
          <!-- Los datos sensibles de la tarjeta se validan en el servidor y no se almacenan -->
          <form method="POST" autocomplete="off">
            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="input-group">
              <label for="cardName">Nombre en la tarjeta</label>
              <div class="input-field"><input type="text" id="cardName" name="nombre_tarjeta" maxlength="100" value="<?php echo htmlspecialchars($card_form['nombre_tarjeta'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            </div>
            <div class="input-group">
              <label for="cardNumber">Número de tarjeta</label>
              <div class="input-field"><input type="text" id="cardNumber" name="numero_tarjeta" inputmode="numeric" maxlength="19" pattern="[0-9 ]{13,23}" value="<?php echo htmlspecialchars($card_form['numero_tarjeta'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="•••• •••• •••• ••••" required></div>
            </div>
            <div class="form-row">
              <div class="input-group">
                <label for="cardExp">Vence</label>
                <div class="input-field"><input type="text" id="cardExp" name="vencimiento" maxlength="7" pattern="(0[1-9]|1[0-2]) ?/ ?[0-9]{2}" value="<?php echo htmlspecialchars($card_form['vencimiento'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="MM / AA" required></div>
              </div>
              <div class="input-group">
                <label for="cardCvv">CVV</label>
                <div class="input-field"><input type="password" id="cardCvv" name="cvv" inputmode="numeric" maxlength="4" pattern="[0-9]{3,4}" value="<?php echo htmlspecialchars($card_form['cvv'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="•••" required></div>
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
          <p style="color: #666;">Esta reservación está <?php echo htmlspecialchars(strtolower($reservacion['estado']), ENT_QUOTES, 'UTF-8'); ?>.</p>
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