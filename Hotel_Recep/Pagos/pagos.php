<?php
require_once '../../config/sesion.php';
verificarAcceso(['recepcionista', 'admin']);   // solo recepción (o admin)

require_once '../../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

$tipos_pago = ['Anticipo', 'Liquidación'];

/** Busca una reservación por su número de confirmación (con huésped, habitación y tarifa). */
function cargarReservacion(PDO $c, string $codigo): ?array {
    $st = $c->prepare("
        SELECT r.id_reservacion, r.numero_confirmacion, r.fecha_entrada, r.fecha_salida, r.estado,
               r.numero_habitacion, u.nombre AS nombre_huesped, h.tipo_habitacion, t.precio_noche
        FROM reservacion r
        JOIN usuario u    ON u.id_usuario = r.id_huesped
        JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
        JOIN tarifa t     ON t.id_tarifa = r.id_tarifa
        WHERE r.numero_confirmacion = ?
    ");
    $st->execute([$codigo]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Total de la estancia (noches x tarifa de la reservación). */
function totalReservacion(array $r): float {
    $noches = max(1, (int) (new DateTime($r['fecha_entrada']))->diff(new DateTime($r['fecha_salida']))->days);
    return round($noches * (float) $r['precio_noche'], 2);
}

/** Suma de lo pagado hasta ahora. */
function totalPagado(PDO $c, int $id_reservacion): float {
    $st = $c->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
    $st->execute([$id_reservacion]);
    return round((float) $st->fetchColumn(), 2);
}

// Aviso tras registrar (se redirige después del POST para no duplicar el pago con F5)
if (($_GET['ok'] ?? '') === '1') {
    $mensaje_exito = "¡Pago registrado correctamente!";
}

$codigo_buscado = strtoupper(trim($_GET['codigo'] ?? ''));

// 1. Registrar un pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_pago') {
    $codigo_buscado = strtoupper(trim($_POST['codigo_reservacion'] ?? ''));
    $tipo_pago = $_POST['tipo_pago'] ?? '';
    $monto = round((float) str_replace(',', '.', $_POST['monto'] ?? '0'), 2);

    if (!in_array($tipo_pago, $tipos_pago, true)) {
        $mensaje_error = "Tipo de pago no válido.";
    } elseif ($monto <= 0) {
        $mensaje_error = "Por favor ingresa un monto válido.";
    } else {
        $res = $codigo_buscado !== '' ? cargarReservacion($conexion, $codigo_buscado) : null;
        if (!$res) {
            $mensaje_error = "No se encontró ninguna reservación con el código: " . $codigo_buscado;
        } elseif ($res['estado'] !== 'Activa') {
            $mensaje_error = "Solo se pueden registrar pagos en reservaciones activas.";
        } else {
            try {
                $conexion->beginTransaction();

                // Se bloquea la reservación mientras se calcula el saldo, para no sobrepasar el total
                $conexion->prepare("SELECT id_reservacion FROM reservacion WHERE id_reservacion = ? FOR UPDATE")
                         ->execute([$res['id_reservacion']]);
                $saldo = round(totalReservacion($res) - totalPagado($conexion, (int) $res['id_reservacion']), 2);

                if ($monto > $saldo) {
                    $conexion->rollBack();
                    $mensaje_error = "El monto ($" . number_format($monto, 2) . ") supera el saldo pendiente ($" . number_format(max(0, $saldo), 2) . ").";
                } else {
                    $conexion->prepare("
                        INSERT INTO pago (monto, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                        VALUES (?, ?, 'Terminal', ?, ?)
                    ")->execute([$monto, $tipo_pago, $res['id_reservacion'], $_SESSION['id_usuario']]);
                    $conexion->commit();

                    header("Location: pagos.php?codigo=" . urlencode($codigo_buscado) . "&ok=1");
                    exit();
                }
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) { $conexion->rollBack(); }
                error_log('Recepción - registrar pago: ' . $e->getMessage());
                $mensaje_error = "No se pudo registrar el pago. Inténtalo de nuevo.";
            }
        }
    }
}

// 2. Datos de la reservación buscada, saldo e historial
$reservacion = null;
$historial_pagos = [];
$costo_total = $total_pagado = $saldo_pendiente = 0.0;

if ($codigo_buscado !== '') {
    try {
        $reservacion = cargarReservacion($conexion, $codigo_buscado);
        if ($reservacion) {
            $costo_total     = totalReservacion($reservacion);
            $total_pagado    = totalPagado($conexion, (int) $reservacion['id_reservacion']);
            $saldo_pendiente = max(0, round($costo_total - $total_pagado, 2));

            $st = $conexion->prepare("
                SELECT p.fecha_hora, p.tipo_pago, p.monto, COALESCE(u.nombre, 'En línea') AS registrado_por
                FROM pago p
                LEFT JOIN usuario u ON u.id_usuario = p.id_recepcionista
                WHERE p.id_reservacion = ?
                ORDER BY p.fecha_hora DESC, p.id_pago DESC
            ");
            $st->execute([$reservacion['id_reservacion']]);
            $historial_pagos = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Recepción - consultar pagos: ' . $e->getMessage());
        $reservacion = null;
        $mensaje_error = "Error al consultar la reservación.";
    }
}

$puede_pagar = $reservacion && $reservacion['estado'] === 'Activa' && $saldo_pendiente > 0;

$nombre_usuario = $_SESSION['nombre'] ?? 'Recepción';
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
      <a href="../Recepcion/recep.php">Reservaciones</a>
      <a href="../Habitaciones/hab.php">Habitaciones</a>
      <a href="pagos.php" class="active">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        <span class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></span>
      </div>
      <a href="../../config/logout.php" title="Cerrar sesión" style="margin-left: 14px; color: inherit;"><i class="fa-solid fa-right-from-bracket"></i></a>
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
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_exito); ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <!-- Buscador de código de reservación -->
      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label>Código de reservación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket"></i>
            <input type="text" name="codigo" maxlength="5" value="<?php echo htmlspecialchars($codigo_buscado); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </form>

      <?php if ($reservacion): ?>
        <!-- Tarjeta de Resumen Financiero del Huésped -->
        <div class="guest-financial-card">
          <div class="guest-name-title"><?php echo htmlspecialchars($reservacion['nombre_huesped']); ?></div>
          <div class="guest-stay-info">
              <?php echo date('d M', strtotime($reservacion['fecha_entrada'])); ?> → <?php echo date('d M Y', strtotime($reservacion['fecha_salida'])); ?> · <?php echo htmlspecialchars($reservacion['tipo_habitacion'] . ' ' . $reservacion['numero_habitacion']); ?> · <?php echo htmlspecialchars($reservacion['estado']); ?>
          </div>

          <div class="financial-rows">
            <div class="fin-row">
              <span>Total</span>
              <b>$<?php echo number_format($costo_total, 2); ?></b>
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

        <?php if ($puede_pagar): ?>
        <!-- Tarjeta de Registrar Pago -->
        <div class="register-payment-box">
          <h2>Registrar pago</h2>

          <form method="POST" action="" id="registerPaymentForm">
            <input type="hidden" name="accion" value="registrar_pago">
            <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">
            <input type="hidden" name="tipo_pago" id="tipoPagoInput" value="Anticipo">

            <div class="input-group">
              <label>Tipo de pago</label>
              <div class="payment-type-options">
                <button type="button" class="type-btn active" data-tipo="Anticipo">Anticipo</button>
                <button type="button" class="type-btn" data-tipo="Liquidación">Liquidación</button>
              </div>
            </div>

            <div class="input-group">
              <label>Monto</label>
              <div class="input-field">
                <i class="fa-regular fa-credit-card"></i>
                <input type="number" step="0.01" min="0.01" max="<?php echo $saldo_pendiente; ?>" name="monto" id="paymentAmountInput" data-saldo="<?php echo $saldo_pendiente; ?>" placeholder="0.00" required>
              </div>
            </div>

            <button type="submit" class="btn-register-pay">Registrar pago</button>
          </form>
        </div>
        <?php elseif ($reservacion['estado'] !== 'Activa'): ?>
          <p style="text-align: center; color: #666; padding: 20px;">Esta reservación está <?php echo htmlspecialchars(strtolower($reservacion['estado'])); ?>; no admite pagos.</p>
        <?php else: ?>
          <p style="text-align: center; color: #3c763d; padding: 20px; font-weight: 600;">Reservación liquidada: no hay saldo pendiente.</p>
        <?php endif; ?>
      <?php elseif ($codigo_buscado !== ''): ?>
        <p style="text-align: center; color: #666; padding: 20px;">No se encontró ninguna reservación con el código ingresado.</p>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">Ingresa el código de la reservación para ver su saldo y registrar pagos.</p>
      <?php endif; ?>

    </section>

    <!-- Columna Derecha: Historial de pagos -->
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
                    <td><?php echo date('d M - H:i', strtotime($pago['fecha_hora'])); ?></td>
                    <td><?php echo htmlspecialchars($pago['tipo_pago']); ?></td>
                    <td>$<?php echo number_format((float) $pago['monto'], 2); ?></td>
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

  <script src="pagos.js"></script>
</body>
</html>
