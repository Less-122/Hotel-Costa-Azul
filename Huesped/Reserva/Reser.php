<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];

// Tipos que se ofrecen. El precio sale de la tarifa vigente en la BD.
$tipos = [
    'Sencilla' => ['titulo' => 'Habitación sencilla', 'img' => 'https://images.unsplash.com/photo-1591088398332-8a7791972843?q=80&w=600&auto=format&fit=crop'],
    'Doble'    => ['titulo' => 'Habitación doble',    'img' => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?q=80&w=600&auto=format&fit=crop'],
];

const MAX_DIAS_ANTICIPACION = 365;
const MAX_NOCHES_ESTANCIA   = 90;

function fecha(string $f): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    if ($t === false) {
        return $f;
    }
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

function validarFechas(string $entrada, string $salida): string {
    $e = DateTime::createFromFormat('!Y-m-d', $entrada);
    $s = DateTime::createFromFormat('!Y-m-d', $salida);

    if (!$e || !$s || $e->format('Y-m-d') !== $entrada || $s->format('Y-m-d') !== $salida) {
        return "Las fechas no son válidas.";
    }

    $hoy_dt    = new DateTime('today');
    $max_fecha = (clone $hoy_dt)->modify('+' . MAX_DIAS_ANTICIPACION . ' days');

    if ($e < $hoy_dt) {
        return "La fecha de entrada no puede ser anterior a hoy.";
    }
    if ($e > $max_fecha || $s > $max_fecha) {
        return "Solo puedes reservar con hasta " . MAX_DIAS_ANTICIPACION . " días de anticipación (máximo " . $max_fecha->format('Y-m-d') . ").";
    }
    if ($s <= $e) {
        return "La salida debe ser posterior a la entrada.";
    }

    $noches_calc = (int) $e->diff($s)->days;
    if ($noches_calc > MAX_NOCHES_ESTANCIA) {
        return "La estancia máxima permitida es de " . MAX_NOCHES_ESTANCIA . " noches.";
    }

    return '';
}

/** Habitaciones libres por tipo en el rango dado: ['Sencilla' => 3, 'Doble' => 0, ...] */
function disponibilidad(PDO $conexion, string $entrada, string $salida): array {
    $solo_libres = ($entrada === date('Y-m-d')) ? " AND h.disponible = 1" : "";
    $stmt = $conexion->prepare(
        "SELECT h.tipo_habitacion, COUNT(*)
         FROM habitacion h
         WHERE h.estado_limpieza <> 'Mantenimiento'{$solo_libres}
         AND NOT EXISTS (
             SELECT 1 FROM reservacion r
             WHERE r.numero_habitacion = h.numero_habitacion
               AND r.estado IN ('Activa', 'En curso')
               AND r.fecha_entrada < :salida
               AND r.fecha_salida  > :entrada
         )
         GROUP BY h.tipo_habitacion"
    );
    $stmt->execute(['entrada' => $entrada, 'salida' => $salida]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$hoy_str      = date('Y-m-d');
$max_fecha_str = (new DateTime('today'))->modify('+' . MAX_DIAS_ANTICIPACION . ' days')->format('Y-m-d');

// Fechas: se validan siempre en el servidor y se conserva lo escrito si hay error
$src     = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$entrada = trim((string) ($src['entrada'] ?? $hoy_str));
$salida  = trim((string) ($src['salida']  ?? date('Y-m-d', strtotime('+2 days'))));

$mensaje_error = validarFechas($entrada, $salida);
$fechas_ok     = ($mensaje_error === '');

$noches = 0;
if ($fechas_ok) {
    $noches = (int) (new DateTime($entrada))->diff(new DateTime($salida))->days;
}

// Tarifas vigentes: ['Sencilla' => ['id_tarifa' => .., 'precio_noche' => ..], ...]
$tarifas = $conexion->query(
    "SELECT tipo_habitacion, id_tarifa, precio_noche FROM tarifa WHERE vigente = 1"
)->fetchAll(PDO::FETCH_UNIQUE);

// RESERVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fechas_ok) {
    $tipo  = $_POST['tipo_habitacion'] ?? '';
    $notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 255) ?: null;

    if (!isset($tipos[$tipo], $tarifas[$tipo])) {
        $mensaje_error = "Tipo de habitación no válido.";
    } else {
        try {
            $conexion->beginTransaction();

            // Se bloquean las habitaciones de ese tipo: si dos huéspedes reservan al mismo
            // tiempo, el segundo espera y ya ve la reservación del primero (sin traslapes).
            $conexion->prepare("SELECT numero_habitacion FROM habitacion WHERE tipo_habitacion = ? FOR UPDATE")
                     ->execute([$tipo]);

            // Primera habitación del tipo sin reservaciones activas o en curso que se traslapen
            $solo_libres = ($entrada === date('Y-m-d')) ? " AND h.disponible = 1" : "";
            $stmt = $conexion->prepare(
                "SELECT h.numero_habitacion
                  FROM habitacion h
                  WHERE h.tipo_habitacion = :tipo
                  AND h.estado_limpieza <> 'Mantenimiento'{$solo_libres}
                    AND NOT EXISTS (
                       SELECT 1 FROM reservacion r
                       WHERE r.numero_habitacion = h.numero_habitacion
                         AND r.estado IN ('Activa', 'En curso')
                         AND r.fecha_entrada < :salida
                         AND r.fecha_salida  > :entrada
                   )
                 ORDER BY h.numero_habitacion
                 LIMIT 1"
            );
            $stmt->execute(['tipo' => $tipo, 'entrada' => $entrada, 'salida' => $salida]);
            $numero_hab = $stmt->fetchColumn();

            $id_politica = $conexion->query(
                "SELECT id_politica FROM politica_cancelacion WHERE vigente = 1 LIMIT 1"
            )->fetchColumn();

            if (!$numero_hab) {
                $mensaje_error = "Ya no hay habitaciones " . strtolower($tipo) . " disponibles en esas fechas.";
            } elseif (!$id_politica) {
                $mensaje_error = "No se puede reservar por el momento: no hay una política de cancelación vigente.";
            } else {
                // Código de confirmación de 5 caracteres que no exista todavía
                $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $existe   = $conexion->prepare("SELECT 1 FROM reservacion WHERE numero_confirmacion = ?");
                do {
                    $codigo = '';
                    for ($i = 0; $i < 5; $i++) {
                        $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                    }
                    $existe->execute([$codigo]);
                } while ($existe->fetchColumn());

                $conexion->prepare(
                    "INSERT INTO reservacion
                        (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal,
                         id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica)
                     VALUES (?, ?, ?, 'Activa', ?, 'En línea', ?, NULL, ?, ?, ?)"
                )->execute([
                    $codigo, $entrada, $salida, $notas,
                    $id_huesped, $numero_hab, $tarifas[$tipo]['id_tarifa'], $id_politica,
                ]);

                $conexion->commit();
                header("Location: Reser.php?ok=" . urlencode($codigo));
                exit;
            }
        } catch (PDOException $e) {
            error_log('Reservar - crear: ' . $e->getMessage());
            $mensaje_error = "No se pudo completar la reservación.";
        }

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
    }
}

// CONFIRMACIÓN (solo se muestra si la reservación es del huésped en sesión)
$confirmada = null;
if (isset($_GET['ok'])) {
    $stmt = $conexion->prepare(
        "SELECT r.numero_confirmacion, r.fecha_entrada, r.fecha_salida, r.numero_habitacion,
                h.tipo_habitacion, t.precio_noche
         FROM reservacion r
         JOIN habitacion h ON r.numero_habitacion = h.numero_habitacion
         JOIN tarifa t     ON r.id_tarifa = t.id_tarifa
         WHERE r.numero_confirmacion = ? AND r.id_huesped = ?"
    );
    $stmt->execute([$_GET['ok'], $id_huesped]);
    $confirmada = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($confirmada) {
        $n = (new DateTime($confirmada['fecha_entrada']))->diff(new DateTime($confirmada['fecha_salida']))->days;
        $confirmada['total'] = $n * $confirmada['precio_noche'];
    }
}

// Disponibilidad solo si las fechas son válidas
$disponibles = $fechas_ok ? disponibilidad($conexion, $entrada, $salida) : [];

$precios      = [];
$tipo_inicial = null;
$preferido    = $_GET['tipo'] ?? '';
foreach ($tipos as $t => $info) {
    if (!isset($tarifas[$t])) {
        continue;
    }
    $precios[$t] = (float) $tarifas[$t]['precio_noche'];
    if ($fechas_ok && ($disponibles[$t] ?? 0) > 0 && ($tipo_inicial === null || $t === $preferido)) {
        $tipo_inicial = $t;
    }
}

// Calcular min inicial de salida a partir de la entrada actual si tiene formato válido
$min_salida_inicial = date('Y-m-d', strtotime('+1 day'));
$dt_ent_check = DateTime::createFromFormat('!Y-m-d', $entrada);
if ($dt_ent_check && $dt_ent_check->format('Y-m-d') === $entrada) {
    $min_salida_inicial = (clone $dt_ent_check)->modify('+1 day')->format('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Reservar estancia</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="Reser.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="Reser.php" class="active">Reservar</a>
      <a href="../Pagar/pagar.php">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
    <div class="nav-user">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <strong><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <span>Huésped</span>
      </div>
      <a href="../../Principal/index.php" class="btn-home" title="Volver al inicio">
        <i class="fa-solid fa-house"></i>
      </a>
    </div>
  </header>
  <main class="main-container">
    <?php if ($confirmada): ?>
      <div style="background: #fff; padding: 40px; border-radius: 8px; max-width: 600px; margin: 40px auto; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 50px; color: #667121; margin-bottom: 15px;"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="color: #102948; margin-bottom: 10px;">¡Reservación confirmada!</h1>
        <p style="color: #666; margin-bottom: 25px;">Gracias, <?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?>. Te esperamos en Costa Azul.</p>
        <div style="background: #f9f6f0; border: 1px dashed #102948; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
          <span style="font-size: 12px; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px;">Tu número de confirmación</span>
          <strong style="font-size: 28px; color: #102948; letter-spacing: 2px;"><?php echo htmlspecialchars($confirmada['numero_confirmacion'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <p style="color: #555; margin-bottom: 5px;">
          <?php echo fecha($confirmada['fecha_entrada']); ?> → <?php echo fecha($confirmada['fecha_salida']); ?>
          · Hab. <?php echo htmlspecialchars($confirmada['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($confirmada['tipo_habitacion'], ENT_QUOTES, 'UTF-8'); ?>)
        </p>
        <p style="color: #102948; font-weight: 600; margin-bottom: 25px;">Total: $<?php echo number_format($confirmada['total'], 2); ?></p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <a href="Reser.php" style="background: #102948; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Hacer otra reserva</a>
          <a href="../Pagar/pagar.php?codigo=<?php echo urlencode($confirmada['numero_confirmacion']); ?>" style="background: #667121; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Pagar ahora</a>
        </div>
      </div>
    <?php else: ?>
      <section class="left-section">
        <h1>Reserva tu estancia</h1>

        <?php if ($mensaje_error): ?>
          <div style="background: #f2dede; color: #a94442; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <form method="GET" class="filter-box">
          <div class="date-input-group">
            <label for="entrada">Entrada</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" id="entrada" name="entrada" min="<?php echo htmlspecialchars($hoy_str, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($entrada, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>
          <div class="date-input-group">
            <label for="salida">Salida</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" id="salida" name="salida" min="<?php echo htmlspecialchars($min_salida_inicial, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($max_fecha_str, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($salida, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>
          <button type="submit" class="btn-availability"><i class="fa-solid fa-magnifying-glass"></i> Buscar disponibilidad</button>
        </form>

        <div class="rooms-list">
          <form method="POST" id="formReserva">
            <input type="hidden" name="entrada" value="<?php echo htmlspecialchars($entrada, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="salida" value="<?php echo htmlspecialchars($salida, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tipo_habitacion" id="inputTipoHabitacion" value="<?php echo htmlspecialchars((string) $tipo_inicial, ENT_QUOTES, 'UTF-8'); ?>">

            <?php foreach ($precios as $tipo => $precio): ?>
              <?php $libre = $fechas_ok && (($disponibles[$tipo] ?? 0) > 0); ?>
              <div class="room-card <?php echo ($tipo === $tipo_inicial) ? 'selected' : ''; ?>"
                   <?php echo $libre ? "onclick=\"seleccionarHabitacion('" . htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') . "', this)\"" : 'style="opacity: .55; cursor: not-allowed;"'; ?>>
                <img src="<?php echo htmlspecialchars($tipos[$tipo]['img'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($tipos[$tipo]['titulo'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="room-details">
                  <h2><?php echo htmlspecialchars($tipos[$tipo]['titulo'], ENT_QUOTES, 'UTF-8'); ?></h2>
                  <?php if (!$fechas_ok): ?>
                    <span class="badge" style="background: #f1f5f9; color: #475569;">Corrige las fechas para consultar</span>
                  <?php elseif ($libre): ?>
                    <span class="badge available">Disponible</span>
                  <?php else: ?>
                    <span class="badge" style="background: #f2dede; color: #a94442;">No disponible en estas fechas</span>
                  <?php endif; ?>
                  <p><?php echo $fechas_ok ? $noches . ' noche(s) · ' : ''; ?>$<?php echo number_format($precio, 2); ?> por noche</p>
                </div>
              </div>
            <?php endforeach; ?>
          </form>
        </div>
      </section>

      <section class="right-section">
        <div class="card-box">
          <h2>Tus datos y resumen</h2>
          <span class="step-subtitle">Huésped: <strong><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></span>

          <div class="summary-details">
            <?php if ($fechas_ok): ?>
              <div class="detail-row">
                <span>Estancia</span>
                <span><?php echo $noches; ?> noche(s)</span>
              </div>
              <div class="detail-row">
                <span>Fechas</span>
                <span><?php echo fecha($entrada); ?> al <?php echo fecha($salida); ?></span>
              </div>
              <?php if ($tipo_inicial): ?>
                <div class="detail-row">
                  <span>Habitación</span>
                  <span id="lblTipoSeleccionado"><?php echo htmlspecialchars($tipo_inicial, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="summary-total">
                  <span>Total estimado</span>
                  <strong>$<span id="lblTotalCalculado"><?php echo number_format($noches * $precios[$tipo_inicial], 2); ?></span></strong>
                </div>
              <?php else: ?>
                <p class="summary-empty">No hay habitaciones disponibles en estas fechas. Prueba con otras.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="summary-empty">Verifica que las fechas de entrada y salida sean válidas para ver el resumen.</p>
            <?php endif; ?>
          </div>

          <div class="input-group">
            <label for="notas">Solicitudes especiales (opcional)</label>
            <textarea id="notas" name="notas" form="formReserva" maxlength="255" placeholder="Ej. Cama extra..."></textarea>
          </div>

          <button type="submit" form="formReserva" class="modal-btn" <?php echo ($fechas_ok && $tipo_inicial) ? '' : 'disabled'; ?>>Reservar ahora</button>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script>
    const precios = <?php echo json_encode($precios); ?>;
    const noches  = <?php echo (int) $noches; ?>;
    const maxFecha = <?php echo json_encode($max_fecha_str); ?>;

    const inputEntrada = document.getElementById('entrada');
    const inputSalida  = document.getElementById('salida');

    function sumarDiasISO(fechaISO, dias) {
      const partes = fechaISO.split('-');
      if (partes.length !== 3) return null;
      const d = new Date(Date.UTC(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]) + dias));
      if (Number.isNaN(d.getTime())) return null;
      return d.toISOString().slice(0, 10);
    }

    function actualizarMinSalida() {
      if (!inputEntrada || !inputSalida || !inputEntrada.value) return;
      const minSal = sumarDiasISO(inputEntrada.value, 1);
      if (!minSal) return;

      inputSalida.min = minSal;
      if (inputSalida.max && inputSalida.min > inputSalida.max) {
        inputSalida.min = inputSalida.max;
      }
      if (inputSalida.value && inputSalida.value < inputSalida.min) {
        inputSalida.value = inputSalida.min;
      }
    }

    if (inputEntrada && inputSalida) {
      actualizarMinSalida();
      inputEntrada.addEventListener('change', actualizarMinSalida);
    }

    function seleccionarHabitacion(tipo, card) {
      document.getElementById('inputTipoHabitacion').value = tipo;
      document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      const lblTipo = document.getElementById('lblTipoSeleccionado');
      const lblTot  = document.getElementById('lblTotalCalculado');
      if (lblTipo) lblTipo.innerText = tipo;
      if (lblTot && precios[tipo] !== undefined) {
        lblTot.innerText = (precios[tipo] * noches).toFixed(2);
      }
    }
  </script>
</body>
</html>