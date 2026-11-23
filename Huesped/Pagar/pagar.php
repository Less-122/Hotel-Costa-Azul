<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../config/reservaciones.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = strtoupper(trim($_REQUEST['codigo'] ?? ''));
$modo_cancelar_penalizacion = isset($_REQUEST['cancelar_penalizacion']) && (string) $_REQUEST['cancelar_penalizacion'] === '1';

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
    $r['costo']  = costoEstancia((float) $r['precio_noche'], $r['fecha_entrada'], $r['fecha_salida']);
    $r['pagado'] = round((float) $r['pagado'], 2);
    // Solo las reservaciones abiertas tienen saldo por pagar
    $r['saldo']  = in_array($r['estado'], ESTADOS_ABIERTOS, true)
        ? max(0.0, round($r['costo'] - $r['pagado'], 2))
        : 0.0;
    return $r;
}

$mensaje_exito = isset($_GET['ok']) ? "Pago procesado correctamente." : '';
$mensaje_error = '';
if ($flash = flashGet()) {
    if ($flash['tipo'] === 'ok') {
        $mensaje_exito = $flash['msg'];
    } else {
        $mensaje_error = $flash['msg'];
    }
}

$card_form = [
    'nombre_tarjeta' => $_SESSION['nombre'] ?? '',
    'numero_tarjeta' => '',
    'vencimiento'    => ' / ',
    'cvv'            => '',
];

// PAGAR (reservaciones 'Activa' o 'En curso', igual que en recepción)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? null)) {
        $mensaje_error = "Error de validación de seguridad. Por favor, recarga la página e inténtalo de nuevo.";
    } else {
        $monto_raw = trim((string) ($_POST['monto'] ?? ''));
        $monto_valido = preg_match('/^\d+(?:\.\d{1,2})?$/', $monto_raw) === 1;
        $monto = $monto_valido ? round((float) $monto_raw, 2) : 0.0;

        $card_form['nombre_tarjeta'] = trim($_POST['nombre_tarjeta'] ?? '');
        $card_form['numero_tarjeta'] = trim($_POST['numero_tarjeta'] ?? '');
        $card_form['vencimiento']    = trim($_POST['vencimiento'] ?? '');
        $card_form['cvv']            = trim($_POST['cvv'] ?? '');

        $digitos_tarjeta = preg_replace('/\D+/', '', $card_form['numero_tarjeta']);

        if (!$monto_valido || $monto <= 0) {
            $mensaje_error = "Ingresa un monto numérico válido mayor a 0 (hasta 2 decimales).";
        } elseif ($card_form['nombre_tarjeta'] === '' || mb_strlen($card_form['nombre_tarjeta']) > 100) {
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

                if (!$r) {
                    $mensaje_error = "La reservación no existe o no pertenece a tu cuenta.";
                } elseif (!in_array($r['estado'], ['Activa', 'En curso'], true)) {
                    $mensaje_error = "El pago en línea solo está disponible para reservaciones Activas o En curso (estado actual: "
                                   . $r['estado'] . ").";
                } elseif ($monto <= 0 || $monto > $r['saldo']) {
                    $mensaje_error = "El monto debe ser mayor a 0 y no superar tu saldo pendiente ($"
                                   . number_format($r['saldo'], 2) . ").";
                } else {
                    $infoPen = ($r['estado'] === 'Activa')
                        ? estadoPenalizacionCancelacion($conexion, (int) $r['id_reservacion'])
                        : null;
                    $pendientePen = $infoPen ? (float) $infoPen['pendiente'] : 0.0;
                    $debeCancelarTrasPago = ($r['estado'] === 'Activa')
                        && ($pendientePen > 0.0)
                        && ($modo_cancelar_penalizacion || $pendientePen > 0.0);

                    if ($debeCancelarTrasPago && $monto < $pendientePen) {
                        $mensaje_error = "Para cancelar esta reservación debes pagar al menos la penalización pendiente de $"
                                       . number_format($pendientePen, 2) . ".";
                    } else {
                        $tipo = (abs($monto - $r['saldo']) < 0.005) ? 'Liquidación' : 'Anticipo';

                        $conexion->prepare(
                            "INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista)
                             VALUES (?, NOW(), ?, 'Pasarela en línea', ?, NULL)"
                        )->execute([$monto, $tipo, $r['id_reservacion']]);

                        $conexion->commit();

                        if ($debeCancelarTrasPago) {
                            try {
                                $resCancel = ejecutarCancelacion($conexion, (int) $r['id_reservacion'], [
                                    'id_actor'                 => $id_huesped,
                                    'id_huesped'               => $id_huesped,
                                    'id_staff'                 => null,
                                    'canal'                    => 'Sistema',
                                    'exigir_pago_penalizacion' => true,
                                ]);
                                flashSet('ok', 'Pago de penalización registrado. ' . mensajeCancelacion($resCancel));
                                header("Location: ../Cancelar/Cance.php?codigo=" . urlencode($codigo));
                                exit;
                            } catch (RuntimeException $e) {
                                $mensaje_error = "El pago se registró, pero no se pudo completar la cancelación: " . $e->getMessage();
                            } catch (PDOException $e) {
                                error_log('Pagar - cancelar tras penalización [' . $e->getCode() . ']: ' . $e->getMessage());
                                $mensaje_error = "El pago se registró, pero ocurrió un error al completar la cancelación. Inténtalo desde Cancelar reservación.";
                            }
                        } else {
                            header("Location: pagar.php?codigo=" . urlencode($codigo) . "&ok=1");
                            exit;
                        }
                    }
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
}

// BUSCAR (solo entre las reservaciones del huésped en sesión)
$reservacion = ($codigo !== '') ? buscarReservacion($conexion, $codigo, $id_huesped) : null;
$infoPenUi   = ($reservacion && $reservacion['estado'] === 'Activa')
    ? estadoPenalizacionCancelacion($conexion, (int) $reservacion['id_reservacion'])
    : null;
$falta_penalizacion_ui = $infoPenUi ? (float) $infoPenUi['pendiente'] : 0.0;
$monto_sugerido_ui = ($falta_penalizacion_ui > 0 && $reservacion)
    ? min((float) $reservacion['saldo'], $falta_penalizacion_ui)
    : ($reservacion ? (float) $reservacion['saldo'] : 0.0);

$puede_pagar = $reservacion
    && in_array($reservacion['estado'], ESTADOS_ABIERTOS, true)
    && $reservacion['saldo'] > 0;
$mis_reservaciones = ($codigo === '') ? reservacionesDelHuesped($conexion, $id_huesped) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Pagar mi reservación</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="pagar.css">
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="../MisReservas/misreservas.php">Mis reservaciones</a>
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
      <p class="subtitle">Abona un anticipo o liquida tu saldo en línea (reservaciones activas o en curso).</p>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
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

      <?php if ($codigo === '' && $mis_reservaciones): ?>
        <div class="quick-list">
          <p class="quick-list-title">¿No recuerdas tu código? Elige una de tus reservaciones con saldo:</p>
          <?php $hay_saldo = false; foreach ($mis_reservaciones as $mr): ?>
            <?php if ($mr['saldo'] <= 0) { continue; } $hay_saldo = true; ?>
            <a class="quick-item" href="pagar.php?codigo=<?php echo urlencode($mr['numero_confirmacion']); ?>">
              <span><b><?php echo htmlspecialchars($mr['numero_confirmacion'], ENT_QUOTES, 'UTF-8'); ?></b> · <?php echo fecha($mr['fecha_entrada'], true); ?></span>
              <span>Saldo $<?php echo number_format($mr['saldo'], 2); ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$hay_saldo): ?>
            <p class="quick-empty">No tienes reservaciones con saldo pendiente.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($reservacion): ?>
        <div class="reservation-summary-card">
          <div class="res-guest-name"><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="res-info-details">
              <?php echo fecha($reservacion['fecha_entrada'], true); ?> → <?php echo fecha($reservacion['fecha_salida'], true); ?>
              · Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?>
              (<?php echo htmlspecialchars($reservacion['tipo_habitacion'], ENT_QUOTES, 'UTF-8'); ?>)
              · Estado: <b><?php echo htmlspecialchars($reservacion['estado'], ENT_QUOTES, 'UTF-8'); ?></b>
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
          <?php if ($falta_penalizacion_ui > 0): ?>
            <p class="info-text" style="margin-top: 10px;">
              Esta reservación tiene una penalización por cancelación tardía pendiente de <b>$<?php echo number_format($falta_penalizacion_ui, 2); ?></b>. Al pagar este monto se completará la cancelación.
            </p>
          <?php endif; ?>
        </div>
      <?php elseif ($codigo !== ''): ?>
        <p class="not-found">No se encontró ninguna reservación tuya con ese código.</p>
      <?php endif; ?>
    </section>

    <aside class="right-section">
      <div class="card-box">
        <?php if ($puede_pagar): ?>
          <h2><?php echo $falta_penalizacion_ui > 0 ? 'Pagar penalización' : 'Datos de la tarjeta'; ?></h2>
          <!-- Los datos sensibles de la tarjeta se validan en el servidor y no se almacenan -->
          <form method="POST" autocomplete="off" id="payReservationForm">
            <?php echo campoCsrf(); ?>
            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($modo_cancelar_penalizacion): ?>
              <input type="hidden" name="cancelar_penalizacion" value="1">
            <?php endif; ?>

            <div class="input-group">
              <label for="cardName">Nombre en la tarjeta</label>
              <div class="input-field"><input type="text" id="cardName" name="nombre_tarjeta" maxlength="100" value="<?php echo htmlspecialchars($card_form['nombre_tarjeta'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            </div>
            <div class="input-group">
              <label for="cardNumber">Número de tarjeta</label>
              <div class="input-field"><input type="text" id="cardNumber" name="numero_tarjeta" inputmode="numeric" maxlength="23" pattern="[0-9 ]{13,23}" value="<?php echo htmlspecialchars($card_form['numero_tarjeta'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="•••• •••• •••• ••••" required></div>
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
            <div class="input-group input-group-spaced">
              <label for="amountInput">
                <?php echo $falta_penalizacion_ui > 0 ? 'Monto de penalización a pagar (mín. $' . number_format($falta_penalizacion_ui, 2) . ')' : 'Monto a abonar'; ?>
              </label>
              <div class="input-field">
                <input type="number" id="amountInput" name="monto" step="0.01"
                       min="<?php echo $falta_penalizacion_ui > 0 ? $falta_penalizacion_ui : '0.01'; ?>"
                       max="<?php echo $reservacion['saldo']; ?>"
                       value="<?php echo $monto_sugerido_ui; ?>" required>
              </div>
            </div>
            <button type="submit" class="btn-pay-submit">
              <i class="fa-solid fa-lock"></i>
              <?php echo $falta_penalizacion_ui > 0 ? 'Pagar y cancelar reservación' : 'Pagar'; ?>
            </button>
          </form>
        <?php elseif ($reservacion && !in_array($reservacion['estado'], ESTADOS_ABIERTOS, true)): ?>
          <h2>Pago en línea no disponible</h2>
          <p class="info-text">Esta reservación está <b><?php echo htmlspecialchars($reservacion['estado'], ENT_QUOTES, 'UTF-8'); ?></b>. El pago en línea solo acepta reservaciones <b>Activas</b> o <b>En curso</b>.</p>
        <?php elseif ($reservacion): ?>
          <h2>Reservación liquidada</h2>
          <p class="info-text">No tienes saldo pendiente.</p>
        <?php else: ?>
          <h2>Datos de la tarjeta</h2>
          <p class="info-text">Busca tu reservación para poder pagar.</p>
        <?php endif; ?>
      </div>
    </aside>
  </main>

  <script>
    (() => {
      const UMBRAL_SLA_MS = 3000;
      const tInicioVista  = performance.now();

      window.addEventListener('load', () => {
        const nav = performance.getEntriesByType('navigation')[0];
        const ms  = nav
          ? Math.round(nav.responseEnd - nav.startTime)
          : Math.round(performance.now() - tInicioVista);

        console.info(`[Rendimiento pagar.php] ${ms} ms | SLA <= 3000ms: ${ms <= UMBRAL_SLA_MS ? 'OK' : 'EXCEDIDO'}`);
        if (ms > UMBRAL_SLA_MS) {
          console.warn(`[SLA >3s] La carga de pagar.php tardó ${ms} ms.`);
        }
      });

      if (typeof window.fetch === 'function') {
        const fetchOriginal = window.fetch.bind(window);
        window.fetch = async (url, options = {}) => {
          const t0  = performance.now();
          const res = await fetchOriginal(url, options);
          const ms  = Math.round(performance.now() - t0);
          if (ms > UMBRAL_SLA_MS) {
            const srvTime = res.headers.get('X-Response-Time') ?? 'N/A';
            console.warn(`[SLA >3s] ${url} tardó ${ms} ms (Servidor: ${srvTime})`);
          }
          return res;
        };
      }

      const codeInput = document.querySelector('#reservationCode');
      if (codeInput) {
        codeInput.addEventListener('input', () => {
          codeInput.value = codeInput.value.toUpperCase();
        });
      }

      const cardNumberInput = document.querySelector('#cardNumber');
      if (cardNumberInput) {
        const formatearTarjeta = (raw) => {
          const digits = raw.replace(/\D/g, '').slice(0, 19);
          return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
        };

        if (cardNumberInput.value) {
          cardNumberInput.value = formatearTarjeta(cardNumberInput.value);
        }

        cardNumberInput.addEventListener('input', () => {
          const formateado = formatearTarjeta(cardNumberInput.value);
          if (cardNumberInput.value !== formateado) {
            cardNumberInput.value = formateado;
          }
        });
      }

      const expInput = document.querySelector('#cardExp');
      if (expInput) {
        const formatearVencimiento = (raw) => {
          const digits = raw.replace(/\D/g, '').slice(0, 4);
          if (digits.length === 0) {
            return ' / ';
          }
          if (digits.length <= 2) {
            return digits + ' / ';
          }
          return digits.slice(0, 2) + ' / ' + digits.slice(2);
        };

        if (!expInput.value.trim()) {
          expInput.value = ' / ';
        }

        expInput.addEventListener('focus', () => {
          if (!expInput.value.trim() || expInput.value === '/') {
            expInput.value = ' / ';
          }
          if (expInput.value === ' / ') {
            expInput.setSelectionRange(0, 0);
          }
        });

        expInput.addEventListener('input', () => {
          expInput.value = formatearVencimiento(expInput.value);
        });
      }

      const payForm = document.querySelector('#payReservationForm');
      if (payForm) {
        payForm.addEventListener('submit', () => {
          const tEnvio = performance.now();
          window.setTimeout(() => {
            const transcurrido = Math.round(performance.now() - tEnvio);
            if (transcurrido > UMBRAL_SLA_MS) {
              console.warn(`[SLA >3s] El procesamiento de pago está tardando más de 3000 ms (${transcurrido} ms).`);
            }
          }, UMBRAL_SLA_MS);
        });
      }
    })();
  </script>
</body>
</html>