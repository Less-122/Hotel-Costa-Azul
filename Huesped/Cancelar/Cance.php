<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = trim($_REQUEST['codigo'] ?? '');

$mensaje_exito = isset($_GET['ok']) ? "La reservación ha sido cancelada correctamente." : '';
$mensaje_error = '';

// 1. Obtener la política de cancelación vigente (ej. RN-01 o la activa en la BD)
$politica_cancelacion = null;
try {
    $stmt_pol = $conexion->prepare("SELECT descripcion, dias_anticipacion FROM politica WHERE tipo = 'Cancelacion' AND vigente = 1 LIMIT 1");
    $stmt_pol->execute();
    $politica_cancelacion = $stmt_pol->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al consultar política de cancelación: " . $e->getMessage());
}

// Valores por defecto si no hay política registrada (ej. mínimo 3 días antes)
$dias_limite_politica = $politica_cancelacion['dias_anticipacion'] ?? 3;
$texto_politica = $politica_cancelacion['descripcion'] ?? "Se permite cancelar sin penalización hasta {$dias_limite_politica} días antes de la fecha de entrada.";

// BUSCAR RESERVACIÓN Y DATOS FINANCIEROS
$reservacion = null;
$costo_total = 0.0;
$total_pagado = 0.0;
$dias_restantes = 0;
$puede_cancelar = false;
$motivo_bloqueo = '';

if ($codigo !== '') {
    try {
        $stmt = $conexion->prepare(
            "SELECT r.id_reservacion, r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                    r.numero_habitacion, t.precio_noche
             FROM reservacion r
             JOIN tarifa t ON r.id_tarifa = t.id_tarifa
             WHERE r.numero_confirmacion = ? AND r.id_huesped = ?"
        );
        $stmt->execute([$codigo, $id_huesped]);
        $reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservacion) {
            // Calcular costo total
            $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / 86400);
            $costo_total = round($noches * (float) $reservacion['precio_noche'], 2);

            // Calcular total pagado
            $stmt_pago = $conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
            $stmt_pago->execute([$reservacion['id_reservacion']]);
            $total_pagado = round((float) $stmt_pago->fetchColumn(), 2);

            // Calcular días restantes para la entrada
            $hoy = new DateTime(date('Y-m-d'));
            $fecha_entrada_dt = new DateTime($reservacion['fecha_entrada']);
            $diferencia = $hoy->diff($fecha_entrada_dt);
            $dias_restantes = (int) $diferencia->format('%r%a'); // Negativo si ya pasó o inició

            // Reglas de validación y bloqueo
            if ($reservacion['estado'] !== 'Activa') {
                $motivo_bloqueo = "La reservación ya se encuentra " . strtolower($reservacion['estado']) . ".";
            } elseif ($dias_restantes < 0) {
                $motivo_bloqueo = "No se puede cancelar: la fecha de entrada ya pasó o la reservación ya dio inicio.";
            } elseif ($dias_restantes < $dias_limite_politica) {
                $motivo_bloqueo = "No se puede cancelar en línea: el plazo límite según la política ({$dias_limite_politica} días antes) ha expirado.";
            } else {
                $puede_cancelar = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Error al buscar reservación para cancelar: " . $e->getMessage());
        $mensaje_error = "Ocurrió un error al consultar la reservación.";
    }
}

// PROCESAR CANCELACIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reservacion && $puede_cancelar) {
    try {
        $stmt_cancel = $conexion->prepare(
            "UPDATE reservacion
             SET estado = 'Cancelada', modificado_por = ?, fecha_modificacion = NOW()
             WHERE numero_confirmacion = ? AND id_huesped = ? AND estado = 'Activa'"
        );
        $stmt_cancel->execute([$id_huesped, $codigo, $id_huesped]);

        if ($stmt_cancel->rowCount() === 1) {
            header("Location: Cance.php?codigo=" . urlencode($codigo) . "&ok=1");
            exit;
        } else {
            $mensaje_error = "No se pudo procesar la cancelación. Verifique el estado de la reserva.";
        }
    } catch (PDOException $e) {
        error_log("Error al cancelar reservación: " . $e->getMessage());
        $mensaje_error = "Error en la base de datos al intentar cancelar.";
    }
}

$cancelada = $reservacion && $reservacion['estado'] === 'Cancelada';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Cancelar reservación</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="Cance.css">
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
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
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
            <div class="detail-row"><span class="label">Monto Pagado</span><span class="value" style="color: #2e6da4; font-weight: bold;">$<?php echo number_format($total_pagado, 2); ?></span></div>
          </div>
        </div>

        <!-- Sección informativa de la Política de Cancelación y Estado -->
        <div class="policy-notice-box" style="background: #fcf8e3; border: 1px solid #faebcc; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; color: #8a6d3b;">
          <strong><i class="fa-solid fa-circle-info"></i> Política de Cancelación (RN-01):</strong>
          <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($texto_politica, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php if (!$cancelada && $total_pagado > 0): ?>
            <p style="margin: 8px 0 0 0; color: #a94442; font-weight: 500;">
              <i class="fa-solid fa-triangle-exclamation"></i> Tienes un monto pagado de $<?php echo number_format($total_pagado, 2); ?>. Al cancelar, acude a recepción para gestionar cualquier aclaración o reembolso aplicable.
            </p>
          <?php endif; ?>
        </div>

        <div class="bottom-actions">
          <a href="../Reserva/Reser.php" class="btn-back">← Volver</a>
          <?php if ($cancelada): ?>
            <button type="button" class="btn-init-cancel" disabled style="background-color: #ccc; cursor: not-allowed;">Ya está cancelada</button>
          <?php elseif (!$puede_cancelar): ?>
            <div style="text-align: right;">
              <button type="button" class="btn-init-cancel" disabled style="background-color: #d9534f; cursor: not-allowed;" title="<?php echo htmlspecialchars($motivo_bloqueo, ENT_QUOTES, 'UTF-8'); ?>">Cancelación no disponible</button>
              <p style="color: #d9534f; font-size: 12px; margin-top: 5px; font-weight: 500;"><?php echo htmlspecialchars($motivo_bloqueo, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          <?php else: ?>
            <button type="button" class="btn-init-cancel" onclick="toggleModal(true)">Cancelar reservación</button>
          <?php endif; ?>
        </div>
      <?php elseif ($codigo !== ''): ?>
        <p style="text-align: center; color: #666; padding: 20px;">No se encontró ninguna reservación tuya con ese número.</p>
      <?php endif; ?>
    </div>
  </main>

  <div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <h2>¿Estás seguro de cancelar?</h2>
      <p>Se cancelará tu reserva y se registrará en el sistema. <?php if ($total_pagado > 0): ?>Recuerda que tienes <b>$<?php echo number_format($total_pagado, 2); ?></b> pagados vinculados a esta estancia.<?php endif; ?></p>
      <form method="POST">
        <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-buttons">
          <button type="button" class="btn-no" onclick="toggleModal(false)">No, volver</button>
          <button type="submit" class="btn-yes">Sí, cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function toggleModal(abrir) {
      document.getElementById('confirmModal').style.display = abrir ? 'flex' : 'none';
    }
  </script>
</body>
</html>