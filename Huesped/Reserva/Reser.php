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

function fecha(string $f): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

function validarFechas(string $entrada, string $salida): string {
    $e = DateTime::createFromFormat('!Y-m-d', $entrada);
    $s = DateTime::createFromFormat('!Y-m-d', $salida);

    if (!$e || !$s || $e->format('Y-m-d') !== $entrada || $s->format('Y-m-d') !== $salida) {
        return "Las fechas no son válidas.";
    }
    if ($e < new DateTime('today')) {
        return "La fecha de entrada no puede ser anterior a hoy.";
    }
    if ($s <= $e) {
        return "La salida debe ser posterior a la entrada.";
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
               AND r.estado = 'Activa'
               AND r.fecha_entrada < :salida
               AND r.fecha_salida  > :entrada
         )
         GROUP BY h.tipo_habitacion"
    );
    $stmt->execute(['entrada' => $entrada, 'salida' => $salida]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Fechas: se validan siempre en el servidor
$src     = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$entrada = $src['entrada'] ?? date('Y-m-d');
$salida  = $src['salida']  ?? date('Y-m-d', strtotime('+2 days'));

$mensaje_error = validarFechas($entrada, $salida);
$fechas_ok     = ($mensaje_error === '');

if (!$fechas_ok) {
    $entrada = date('Y-m-d');
    $salida  = date('Y-m-d', strtotime('+1 day'));
}

$noches = (new DateTime($entrada))->diff(new DateTime($salida))->days;

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

            // Primera habitación del tipo sin reservaciones activas que se traslapen
            $solo_libres = ($entrada === date('Y-m-d')) ? " AND h.disponible = 1" : "";
            $stmt = $conexion->prepare(
                "SELECT h.numero_habitacion
                  FROM habitacion h
                  WHERE h.tipo_habitacion = :tipo
                  AND h.estado_limpieza <> 'Mantenimiento'{$solo_libres}
                    AND NOT EXISTS (
                       SELECT 1 FROM reservacion r
                       WHERE r.numero_habitacion = h.numero_habitacion
                         AND r.estado = 'Activa'
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

// Disponibilidad para las fechas elegidas
$disponibles = $fechas_ok || $_SERVER['REQUEST_METHOD'] === 'GET'
    ? disponibilidad($conexion, $entrada, $salida)
    : [];

$precios      = [];
$tipo_inicial = null;
$preferido = $_GET['tipo'] ?? '';
foreach ($tipos as $t => $info) {
    if (!isset($tarifas[$t])) {
        continue;
    }
    $precios[$t] = (float) $tarifas[$t]['precio_noche'];
    if (($disponibles[$t] ?? 0) > 0 && ($tipo_inicial === null || $t === $preferido)) {
        $tipo_inicial = $t;
    }
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
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="Reser.php" class="active">Reservar</a>
      <a href="../Pagar/pagar.php">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
  </header>

  <main class="main-container">
    <?php if ($confirmada): ?>
      <div style="background: #fff; padding: 40px; border-radius: 8px; max-width: 600px; margin: 40px auto; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 50px; color: #667121; margin-bottom: 15px;"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="color: #102948; margin-bottom: 10px;">¡Reservación confirmada!</h1>
        <p style="color: #666; margin-bottom: 25px;">Gracias, <?php echo htmlspecialchars($_SESSION['nombre']); ?>. Te esperamos en Costa Azul.</p>
        <div style="background: #f9f6f0; border: 1px dashed #102948; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
          <span style="font-size: 12px; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px;">Tu número de confirmación</span>
          <strong style="font-size: 28px; color: #102948; letter-spacing: 2px;"><?php echo htmlspecialchars($confirmada['numero_confirmacion']); ?></strong>
        </div>
        <p style="color: #555; margin-bottom: 5px;">
          <?php echo fecha($confirmada['fecha_entrada']); ?> → <?php echo fecha($confirmada['fecha_salida']); ?>
          · Hab. <?php echo htmlspecialchars($confirmada['numero_habitacion']); ?> (<?php echo htmlspecialchars($confirmada['tipo_habitacion']); ?>)
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
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($mensaje_error); ?>
          </div>
        <?php endif; ?>

        <form method="GET" class="filter-box">
          <div class="date-input-group">
            <label for="entrada">Entrada</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" id="entrada" name="entrada" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($entrada); ?>" required>
            </div>
          </div>
          <div class="date-input-group">
            <label for="salida">Salida</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" id="salida" name="salida" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo htmlspecialchars($salida); ?>" required>
            </div>
          </div>
          <button type="submit" class="btn-availability"><i class="fa-solid fa-magnifying-glass"></i> Buscar disponibilidad</button>
        </form>

        <div class="rooms-list">
          <form method="POST" id="formReserva">
            <input type="hidden" name="entrada" value="<?php echo htmlspecialchars($entrada); ?>">
            <input type="hidden" name="salida" value="<?php echo htmlspecialchars($salida); ?>">
            <input type="hidden" name="tipo_habitacion" id="inputTipoHabitacion" value="<?php echo htmlspecialchars((string) $tipo_inicial); ?>">

            <?php foreach ($precios as $tipo => $precio): ?>
              <?php $libre = ($disponibles[$tipo] ?? 0) > 0; ?>
              <div class="room-card <?php echo ($tipo === $tipo_inicial) ? 'selected' : ''; ?>"
                   <?php echo $libre ? "onclick=\"seleccionarHabitacion('$tipo', this)\"" : 'style="opacity: .55; cursor: not-allowed;"'; ?>>
                <img src="<?php echo $tipos[$tipo]['img']; ?>" alt="<?php echo htmlspecialchars($tipos[$tipo]['titulo']); ?>">
                <div class="room-details">
                  <h2><?php echo htmlspecialchars($tipos[$tipo]['titulo']); ?></h2>
                  <?php if ($libre): ?>
                    <span class="badge available">Disponible</span>
                  <?php else: ?>
                    <span class="badge" style="background: #f2dede; color: #a94442;">No disponible en estas fechas</span>
                  <?php endif; ?>
                  <p><?php echo $noches; ?> noche(s) · $<?php echo number_format($precio, 2); ?> por noche</p>
                </div>
              </div>
            <?php endforeach; ?>
          </form>
        </div>
      </section>

      <section class="right-section">
        <div class="summary-card">
          <h3>Tus datos y resumen</h3>
          <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Huésped: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></p>
          <div class="summary-details">
            <p>Estancia: <strong><?php echo $noches; ?> noche(s)</strong></p>
            <p>Del <?php echo fecha($entrada); ?> al <?php echo fecha($salida); ?></p>
            <?php if ($tipo_inicial): ?>
              <p style="margin-top: 10px; font-size: 16px; color: #102948;">Habitación: <strong id="lblTipoSeleccionado"><?php echo htmlspecialchars($tipo_inicial); ?></strong></p>
              <p style="font-size: 18px; font-weight: bold; color: #667121; margin-top: 5px;">Total estimado: $<span id="lblTotalCalculado"><?php echo number_format($noches * $precios[$tipo_inicial], 2); ?></span></p>
            <?php else: ?>
              <p style="margin-top: 10px; color: #a94442;">No hay habitaciones disponibles en estas fechas. Prueba con otras.</p>
            <?php endif; ?>
          </div>
          <div style="margin-top: 15px;">
            <label for="notas" style="font-size: 13px; font-weight: 500;">Solicitudes especiales (opcional)</label>
            <textarea id="notas" name="notas" form="formReserva" maxlength="255" placeholder="Ej. Cama extra..." style="width: 100%; height: 70px; margin-top: 5px; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
          </div>
          <button type="submit" form="formReserva" <?php echo $tipo_inicial ? '' : 'disabled'; ?>
                  style="width: 100%; margin-top: 20px; background: #102948; color: #fff; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Reservar ahora</button>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script>
    const precios = <?php echo json_encode($precios); ?>;
    const noches  = <?php echo (int) $noches; ?>;

    function seleccionarHabitacion(tipo, card) {
      document.getElementById('inputTipoHabitacion').value = tipo;
      document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      document.getElementById('lblTipoSeleccionado').innerText = tipo;
      document.getElementById('lblTotalCalculado').innerText = (precios[tipo] * noches).toFixed(2);
    }
  </script>
</body>
</html>