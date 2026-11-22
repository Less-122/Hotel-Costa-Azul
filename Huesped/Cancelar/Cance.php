<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../config/reservaciones.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = strtoupper(trim((string) ($_REQUEST['codigo'] ?? '')));

$mensaje_exito = '';
$mensaje_error = '';
if ($flash = flashGet()) {
    if ($flash['tipo'] === 'ok') {
        $mensaje_exito = $flash['msg'];
    } else {
        $mensaje_error = $flash['msg'];
    }
}

$reservacion          = null;
$texto_politica       = 'Cancelación gratuita hasta 72 horas antes; después se cobra la primera noche.';
$costo_total          = 0.0;
$total_pagado         = 0.0;
$monto_penalizacion   = 0.0;
$monto_reembolso      = 0.0;
$fuera_plazo_gratuito = false;
$puede_cancelar       = false;
$motivo_bloqueo       = '';
$mis_reservaciones    = [];

try {
    if ($codigo !== '') {
        $stmt = $conexion->prepare(
            "SELECT r.id_reservacion, r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                    r.numero_habitacion, t.precio_noche,
                    p.horas_anticipacion, p.cobra_primera_noche,
                    p.descripcion AS politica_descripcion
             FROM reservacion r
             JOIN tarifa t ON r.id_tarifa = t.id_tarifa
             LEFT JOIN politica_cancelacion p ON r.id_politica = p.id_politica
             WHERE r.numero_confirmacion = ? AND r.id_huesped = ?"
        );
        $stmt->execute([$codigo, $id_huesped]);
        $reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservacion) {
            $eval = evaluarCancelacion($reservacion, new DateTime());
            if ($eval['reglas']['descripcion'] !== '') {
                $texto_politica = $eval['reglas']['descripcion'];
            }
            $puede_cancelar       = $eval['puede'];
            $motivo_bloqueo       = $eval['motivo'];
            $monto_penalizacion   = $eval['penalizacion'];
            $fuera_plazo_gratuito = $eval['fuera_plazo'];

            $costo_total = costoEstancia((float) $reservacion['precio_noche'], $reservacion['fecha_entrada'], $reservacion['fecha_salida']);
            $stmt_pago = $conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
            $stmt_pago->execute([$reservacion['id_reservacion']]);
            $total_pagado = round((float) $stmt_pago->fetchColumn(), 2);

            if ($puede_cancelar) {
                $monto_reembolso = liquidarCancelacion($total_pagado, $monto_penalizacion)['reembolso'];
            }
        }
    } else {
        $mis_reservaciones = reservacionesDelHuesped($conexion, $id_huesped);
    }
} catch (PDOException $e) {
    error_log('Cance - consultar: ' . $e->getMessage());
    $mensaje_error = 'Ocurrió un error al consultar la reservación.';
}

// PROCESAR CANCELACIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $mensaje_error = 'Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.';
    } elseif ($reservacion && $puede_cancelar) {
        try {
            $res = ejecutarCancelacion($conexion, (int) $reservacion['id_reservacion'], [
                'id_actor'   => $id_huesped,
                'id_huesped' => $id_huesped,
                'id_staff'   => null,
                'canal'      => 'Sistema',
            ]);
            flashSet('ok', mensajeCancelacion($res));
            header('Location: Cance.php?codigo=' . urlencode($codigo));
            exit;
        } catch (RuntimeException $e) {
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Cance - cancelar [' . $e->getCode() . ']: ' . $e->getMessage());
            $mensaje_error = 'Error en la base de datos al intentar cancelar. Inténtalo de nuevo o acude a recepción.';
        }
    }
}

$cancelada = $reservacion && $reservacion['estado'] === 'Cancelada';
$texto_devolucion_ui = $monto_reembolso > 0 ? '-$' . number_format($monto_reembolso, 2) : '$0.00';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Cancelar reservación</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="Cance.css?v=<?php echo @filemtime(__DIR__ . '/Cance.css'); ?>">
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="../MisReservas/misreservas.php">Mis reservaciones</a>
      <a href="../Pagar/pagar.php">Pagar</a>
      <a href="Cance.php" class="active">Cancelar reservación</a>
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
    <div class="cancel-card">
      <h1>Cancelar reservación</h1>
      <p class="subtitle">Escribe tu número de confirmación para consultar las condiciones y cancelar tu reserva.</p>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="GET" class="search-form">
        <div class="input-group">
          <label for="codigo">Número de confirmación</label>
          <div class="input-field">
            <i class="fa-solid fa-ticket icon-left"></i>
            <input type="text" id="codigo" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($codigo === '' && $mis_reservaciones): ?>
        <div class="quick-list">
          <p class="quick-list-title">¿No recuerdas tu código? Elige una de tus reservaciones:</p>
          <?php foreach ($mis_reservaciones as $mr): ?>
            <?php if ($mr['estado'] !== 'Activa') { continue; } ?>
            <a class="quick-item" href="Cance.php?codigo=<?php echo urlencode($mr['numero_confirmacion']); ?>">
              <span><b><?php echo htmlspecialchars($mr['numero_confirmacion'], ENT_QUOTES, 'UTF-8'); ?></b> · <?php echo fecha($mr['fecha_entrada'], true); ?> → <?php echo fecha($mr['fecha_salida'], true); ?></span>
              <span>Hab. <?php echo htmlspecialchars($mr['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($reservacion): ?>
        <div class="reservation-box">
          <div class="res-header">
            <h2>Reservación <span><?php echo htmlspecialchars($reservacion['numero_confirmacion'], ENT_QUOTES, 'UTF-8'); ?></span></h2>
            <span class="<?php echo $cancelada ? 'badge-cancelled' : 'badge-active'; ?>"><?php echo htmlspecialchars($reservacion['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="res-details">
            <div class="detail-row"><span class="label">Huésped</span><span class="value"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="detail-row"><span class="label">Fechas</span><span class="value"><?php echo htmlspecialchars($reservacion['fecha_entrada'], ENT_QUOTES, 'UTF-8'); ?> al <?php echo htmlspecialchars($reservacion['fecha_salida'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="detail-row"><span class="label">Habitación</span><span class="value">Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="detail-row"><span class="label">Costo Total</span><span class="value">$<?php echo number_format($costo_total, 2); ?></span></div>
            <div class="detail-row"><span class="label">Monto Pagado</span><span class="value value-paid">$<?php echo number_format($total_pagado, 2); ?></span></div>
          </div>
        </div>

        <div class="policy-notice-box">
          <strong><i class="fa-solid fa-circle-info"></i> Política de Cancelación aplicable a esta reserva:</strong>
          <p class="policy-text-main"><?php echo htmlspecialchars($texto_politica, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php if (!$cancelada && $fuera_plazo_gratuito && $monto_penalizacion > 0): ?>
            <p class="policy-warning">
              <i class="fa-solid fa-circle-exclamation"></i> El plazo de cancelación gratuita ya venció. Si cancelas ahora, se aplicará el cobro de la primera noche por <b>$<?php echo number_format($monto_penalizacion, 2); ?></b>.
            </p>
          <?php endif; ?>
          <?php if (!$cancelada && $total_pagado > 0): ?>
            <p class="policy-refund">
              <i class="fa-solid fa-rotate-left"></i> Monto pagado actual: <b>$<?php echo number_format($total_pagado, 2); ?></b>. Al cancelar se registrará una devolución de <b><?php echo $texto_devolucion_ui; ?></b>.
            </p>
          <?php endif; ?>
        </div>

        <div class="bottom-actions">
          <a href="../Reserva/Reser.php" class="btn-back">← Volver</a>
          <?php if ($cancelada): ?>
            <button type="button" class="btn-init-cancel btn-disabled" disabled>Ya está cancelada</button>
          <?php elseif (!$puede_cancelar): ?>
            <div class="cancel-blocked">
              <button type="button" class="btn-init-cancel btn-blocked" disabled title="<?php echo htmlspecialchars($motivo_bloqueo, ENT_QUOTES, 'UTF-8'); ?>">Cancelación no disponible</button>
              <p class="cancel-blocked-text"><?php echo htmlspecialchars($motivo_bloqueo, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          <?php else: ?>
            <button type="button" class="btn-init-cancel" onclick="toggleModal(true)">
              <?php echo $monto_penalizacion > 0 ? 'Cancelar con cargo ($' . number_format($monto_penalizacion, 2) . ')' : 'Cancelar reservación'; ?>
            </button>
          <?php endif; ?>
        </div>
      <?php elseif ($codigo !== '' && $mensaje_error === ''): ?>
        <p class="not-found">No se encontró ninguna reservación tuya con ese número.</p>
      <?php endif; ?>
    </div>
  </main>

  <?php if ($reservacion && $puede_cancelar): ?>
  <div id="confirmModal" class="modal-overlay">
    <div class="modal-content">
      <h2>¿Estás seguro de cancelar?</h2>
      <p>
        Se cancelará tu reserva y se registrará en el sistema.
        <?php if ($monto_penalizacion > 0): ?>
          <br><span class="modal-penalty">Al haber vencido el plazo gratuito, se aplicará un cargo de $<?php echo number_format($monto_penalizacion, 2); ?> correspondiente a la primera noche.</span>
        <?php endif; ?>
        <?php if ($total_pagado > 0): ?>
          <br>Monto pagado: <b>$<?php echo number_format($total_pagado, 2); ?></b> · Devolución a registrar: <b><?php echo $texto_devolucion_ui; ?></b>.
        <?php endif; ?>
      </p>
      <form method="POST" id="cancelReservationForm" action="Cance.php?codigo=<?php echo urlencode($codigo); ?>">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-buttons">
          <button type="button" class="btn-no" onclick="toggleModal(false)">No, volver</button>
          <button type="submit" class="btn-yes">
            <?php echo $monto_penalizacion > 0 ? 'Sí, cancelar y aceptar cargo' : 'Sí, cancelar'; ?>
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function toggleModal(abrir) {
      document.getElementById('confirmModal').classList.toggle('is-open', abrir);
    }

    (() => {
      const UMBRAL_SLA_MS = 3000;
      const tInicioVista  = performance.now();

      window.addEventListener('load', () => {
        const nav = performance.getEntriesByType('navigation')[0];
        const ms  = nav
          ? Math.round(nav.responseEnd - nav.startTime)
          : Math.round(performance.now() - tInicioVista);

        console.info(`[Rendimiento Cance.php] ${ms} ms | SLA <= 3000ms: ${ms <= UMBRAL_SLA_MS ? 'OK' : 'EXCEDIDO'}`);
        if (ms > UMBRAL_SLA_MS) {
          console.warn(`[SLA >3s] La carga de Cance.php tardó ${ms} ms.`);
        }
      });

      const cancelForm = document.querySelector('#cancelReservationForm');
      if (cancelForm) {
        cancelForm.addEventListener('submit', () => {
          const tEnvio = performance.now();
          window.setTimeout(() => {
            const transcurrido = Math.round(performance.now() - tEnvio);
            if (transcurrido > UMBRAL_SLA_MS) {
              console.warn(`[SLA >3s] La cancelación está tardando más de 3000 ms (${transcurrido} ms).`);
            }
          }, UMBRAL_SLA_MS);
        });
      }
    })();
  </script>
</body>
</html>