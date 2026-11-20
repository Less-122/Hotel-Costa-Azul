<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = trim($_REQUEST['codigo'] ?? '');

$mensaje_exito = '';
if (isset($_GET['ok'])) {
    $pen_aplicada = isset($_GET['penalizacion']) ? (float) $_GET['penalizacion'] : 0.0;
    $dev_aplicada = isset($_GET['devolucion']) ? (float) $_GET['devolucion'] : 0.0;

    $mensaje_exito = "La reservación ha sido cancelada correctamente.";
    if ($pen_aplicada > 0) {
        $mensaje_exito .= " Penalización aplicada: $" . number_format($pen_aplicada, 2) . " (primera noche).";
    }
    if ($dev_aplicada > 0) {
        $mensaje_exito .= " Se registró una devolución de -$" . number_format($dev_aplicada, 2) . ".";
    }
}
$mensaje_error = '';

/**
 * Extrae las horas mínimas de anticipación a partir de la descripción de la política.
 * Soporta textos como "48 horas antes", "72 hrs", "3 días antes", etc.
 */
function obtenerHorasAnticipacion(string $descripcion, int $horas_defecto = 72): int {
    if (preg_match('/(\d+)\s*(?:hora|horas|hr|hrs)\b/iu', $descripcion, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/(\d+)\s*(?:d[ií]a|d[ií]as)\b/iu', $descripcion, $m)) {
        return ((int) $m[1]) * 24;
    }
    return $horas_defecto;
}

/**
 * Determina si la política contempla el cobro de la primera noche tras vencer el plazo gratuito.
 */
function politicaCobraPrimeraNoche(string $descripcion): bool {
    return (bool) preg_match('/primera\s+noche|1\s*noche|una\s+noche/iu', $descripcion);
}

// Respaldo solo en caso de que una reservación histórica no tenga id_politica asociado
$texto_politica_respaldo = "Cancelación gratuita hasta 72 horas antes; después se cobra la primera noche.";
try {
    $stmt_pol = $conexion->query(
        "SELECT descripcion FROM politica_cancelacion WHERE vigente = 1 ORDER BY id_politica DESC LIMIT 1"
    );
    $pol_vigente = $stmt_pol ? $stmt_pol->fetch(PDO::FETCH_ASSOC) : false;
    if ($pol_vigente && !empty($pol_vigente['descripcion'])) {
        $texto_politica_respaldo = $pol_vigente['descripcion'];
    }
} catch (PDOException $e) {
    error_log("Error al consultar política de cancelación de respaldo: " . $e->getMessage());
}

// BUSCAR RESERVACIÓN Y DATOS FINANCIEROS
$reservacion           = null;
$texto_politica        = $texto_politica_respaldo;
$horas_limite_politica = obtenerHorasAnticipacion($texto_politica, 72);
$costo_total           = 0.0;
$total_pagado          = 0.0;
$monto_penalizacion    = 0.0;
$monto_reembolso       = 0.0;
$fuera_plazo_gratuito  = false;
$puede_cancelar        = false;
$motivo_bloqueo        = '';

if ($codigo !== '') {
    try {
        $stmt = $conexion->prepare(
            "SELECT r.id_reservacion, r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                    r.numero_habitacion, r.id_politica, r.notas, t.precio_noche,
                    p.descripcion AS politica_descripcion
             FROM reservacion r
             JOIN tarifa t ON r.id_tarifa = t.id_tarifa
             LEFT JOIN politica_cancelacion p ON r.id_politica = p.id_politica
             WHERE r.numero_confirmacion = ? AND r.id_huesped = ?"
        );
        $stmt->execute([$codigo, $id_huesped]);
        $reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservacion) {
            // Usar estrictamente la política guardada en la reservación (r.id_politica)
            if (!empty($reservacion['politica_descripcion'])) {
                $texto_politica = $reservacion['politica_descripcion'];
            }
            $horas_limite_politica = obtenerHorasAnticipacion($texto_politica, 72);

            // Calcular costo total y tarifa por noche
            $precio_noche = round((float) $reservacion['precio_noche'], 2);
            $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / 86400);
            $costo_total = round($noches * $precio_noche, 2);

            // Calcular saldo neto pagado actual
            $stmt_pago = $conexion->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago WHERE id_reservacion = ?");
            $stmt_pago->execute([$reservacion['id_reservacion']]);
            $total_pagado = round((float) $stmt_pago->fetchColumn(), 2);

            // Calcular horas restantes hasta el inicio del día de entrada
            $ahora = new DateTime();
            $fecha_entrada_dt = new DateTime($reservacion['fecha_entrada'] . ' 00:00:00');
            $segundos_restantes = $fecha_entrada_dt->getTimestamp() - $ahora->getTimestamp();
            $horas_restantes = $segundos_restantes / 3600;

            // Reglas de validación y penalización
            if ($reservacion['estado'] !== 'Activa') {
                $motivo_bloqueo = "La reservación ya se encuentra " . strtolower($reservacion['estado']) . ".";
            } elseif ($segundos_restantes <= 0) {
                $motivo_bloqueo = "No se puede cancelar: la fecha de entrada ya pasó o la reservación ya dio inicio.";
            } elseif ($horas_restantes < $horas_limite_politica) {
                if (politicaCobraPrimeraNoche($texto_politica)) {
                    $fuera_plazo_gratuito = true;
                    $monto_penalizacion   = $precio_noche;
                    $puede_cancelar       = true;
                } else {
                    $etiqueta_plazo = ($horas_limite_politica % 24 === 0 && $horas_limite_politica >= 24)
                        ? ($horas_limite_politica / 24) . " día(s)"
                        : $horas_limite_politica . " hora(s)";
                    $motivo_bloqueo = "No se puede cancelar en línea: el plazo límite según la política ({$etiqueta_plazo} antes) ha expirado.";
                }
            } else {
                $puede_cancelar = true;
            }

            if ($puede_cancelar && $total_pagado > 0) {
                $monto_reembolso = max(0.0, round($total_pagado - $monto_penalizacion, 2));
            }
        }
    } catch (PDOException $e) {
        error_log("Error al buscar reservación para cancelar: " . $e->getMessage());
        $mensaje_error = "Ocurrió un error al consultar la reservación.";
    }
}

// PROCESAR CANCELACIÓN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $mensaje_error = "Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.";
    } elseif ($reservacion && $puede_cancelar) {
        try {
            $conexion->beginTransaction();

            $notas_extra = [];

            if ($monto_penalizacion > 0) {
                $notas_extra[] = "[Penalización por cancelación tardía: $" . number_format($monto_penalizacion, 2) . " (1ra noche)]";

                $cargo_a_registrar = max(0.0, round($monto_penalizacion - $total_pagado, 2));
                if ($cargo_a_registrar > 0) {
                    $stmt_cargo = $conexion->prepare(
                        "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                         VALUES (?, NOW(), 'Liquidación', 'Sistema', ?, NULL)"
                    );
                    $stmt_cargo->execute([$cargo_a_registrar, $reservacion['id_reservacion']]);
                }
            }

            if ($monto_reembolso > 0) {
                $monto_negativo = -abs($monto_reembolso);
                $stmt_dev = $conexion->prepare(
                    "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                     VALUES (?, NOW(), 'Liquidación', 'Sistema', ?, NULL)"
                );
                $stmt_dev->execute([$monto_negativo, $reservacion['id_reservacion']]);
                $notas_extra[] = "[Devolución registrada: -$" . number_format($monto_reembolso, 2) . "]";
            }

            $notas_actualizadas = trim(
                ($reservacion['notas'] ?? '') .
                (!empty($notas_extra) ? "\n" . implode("\n", $notas_extra) : '')
            );

            $stmt_cancel = $conexion->prepare(
                "UPDATE reservacion
                 SET estado = 'Cancelada',
                     notas = NULLIF(?, ''),
                     modificado_por = ?,
                     fecha_modificacion = NOW()
                 WHERE numero_confirmacion = ? AND id_huesped = ? AND estado = 'Activa'"
            );
            $stmt_cancel->execute([$notas_actualizadas, $id_huesped, $codigo, $id_huesped]);

            if ($stmt_cancel->rowCount() === 1) {
                $conexion->commit();
                $url_ok = "Cance.php?codigo=" . urlencode($codigo) . "&ok=1";
                if ($monto_penalizacion > 0) {
                    $url_ok .= "&penalizacion=" . urlencode((string) $monto_penalizacion);
                }
                if ($monto_reembolso > 0) {
                    $url_ok .= "&devolucion=" . urlencode((string) $monto_reembolso);
                }
                header("Location: " . $url_ok);
                exit;
            } else {
                $conexion->rollBack();
                $mensaje_error = "No se pudo procesar la cancelación. Verifique el estado de la reserva.";
            }
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log("Error al cancelar reservación: " . $e->getMessage());
            $mensaje_error = "Error en la base de datos al intentar cancelar.";
        }
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

        <div class="policy-notice-box" style="background: #fcf8e3; border: 1px solid #faebcc; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; color: #8a6d3b;">
          <strong><i class="fa-solid fa-circle-info"></i> Política de Cancelación aplicable a esta reserva:</strong>
          <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($texto_politica, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php if (!$cancelada && $fuera_plazo_gratuito && $monto_penalizacion > 0): ?>
            <p style="margin: 8px 0 0 0; color: #a94442; font-weight: 600;">
              <i class="fa-solid fa-circle-exclamation"></i> El plazo de cancelación gratuita ya venció. Si cancelas ahora, se aplicará el cobro de la primera noche por <b>$<?php echo number_format($monto_penalizacion, 2); ?></b>.
            </p>
          <?php endif; ?>
          <?php if (!$cancelada && $total_pagado > 0): ?>
            <p style="margin: 8px 0 0 0; color: #2e6da4; font-weight: 500;">
              <i class="fa-solid fa-rotate-left"></i> Monto pagado actual: <b>$<?php echo number_format($total_pagado, 2); ?></b>. Al cancelar se registrará una devolución de <b>-$<?php echo number_format($monto_reembolso, 2); ?></b>.
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
            <button type="button" class="btn-init-cancel" onclick="toggleModal(true)">
              <?php echo $monto_penalizacion > 0 ? 'Cancelar con cargo ($' . number_format($monto_penalizacion, 2) . ')' : 'Cancelar reservación'; ?>
            </button>
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
      <p>
        Se cancelará tu reserva y se registrará en el sistema.
        <?php if ($monto_penalizacion > 0): ?>
          <br><span style="color: #a94442; font-weight: 600;">Al haber vencido el plazo gratuito, se aplicará un cargo de $<?php echo number_format($monto_penalizacion, 2); ?> correspondiente a la primera noche.</span>
        <?php endif; ?>
        <?php if ($total_pagado > 0): ?>
          <br>Monto pagado: <b>$<?php echo number_format($total_pagado, 2); ?></b> · Devolución a registrar: <b>-$<?php echo number_format($monto_reembolso, 2); ?></b>.
        <?php endif; ?>
      </p>
      <form method="POST">
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

  <script>
    function toggleModal(abrir) {
      document.getElementById('confirmModal').style.display = abrir ? 'flex' : 'none';
    }
  </script>
</body>
</html>