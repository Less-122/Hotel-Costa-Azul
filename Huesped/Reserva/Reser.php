<?php
require_once '../../config/conexion.php';
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Huesped') {
    header("Location: ../Login_regis/index.php?error=acceso_denegado");
    exit();
}

$fecha_entrada = $_GET['entrada'] ?? date('Y-m-d');
$fecha_salida = $_GET['salida'] ?? date('Y-m-d', strtotime('+2 days'));

if (strtotime($fecha_salida) <= strtotime($fecha_entrada)) {
    $fecha_salida = date('Y-m-d', strtotime($fecha_entrada . ' + 1 day'));
}

$noches = max(1, (strtotime($fecha_salida) - strtotime($fecha_entrada)) / (60 * 60 * 24));

$precio_sencilla = 850.00;
$precio_doble = 1200.00;
try {
    $stmtTarifas = $conexion->query("SELECT tipo_habitacion, precio_noche FROM tarifa WHERE vigente = 1");
    while ($t = $stmtTarifas->fetch(PDO::FETCH_ASSOC)) {
        if ($t['tipo_habitacion'] === 'Sencilla') $precio_sencilla = (float)$t['precio_noche'];
        if ($t['tipo_habitacion'] === 'Doble') $precio_doble = (float)$t['precio_noche'];
    }
} catch (Exception $e) {}

$errorReserva = '';
$codigoExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_huesped = $_SESSION['id_usuario'];
    $fecha_entrada = $_POST['fecha_entrada'];
    $fecha_salida = $_POST['fecha_salida'];
    $tipo_habitacion_elegida = $_POST['tipo_habitacion'] ?? 'Sencilla';
    $notas = $_POST['notas'] ?? null;
    $canal = 'En línea';
    $id_recepcionista = NULL; 
    
    $numero_confirmacion = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

    try {
        $sqlHab = "SELECT numero_habitacion FROM habitacion WHERE tipo_habitacion = :tipo AND disponible = 1 LIMIT 1";
        $stmtHab = $conexion->prepare($sqlHab);
        $stmtHab->execute([':tipo' => $tipo_habitacion_elegida]);
        $habitacion = $stmtHab->fetch(PDO::FETCH_ASSOC);

        if (!$habitacion) {
            throw new Exception("Lo sentimos, no hay habitaciones de tipo " . $tipo_habitacion_elegida . " disponibles.");
        }
        $numero_habitacion = $habitacion['numero_habitacion'];

        $sqlTarifa = "SELECT id_tarifa FROM tarifa WHERE tipo_habitacion = :tipo AND vigente = 1 LIMIT 1";
        $stmtTarifa = $conexion->prepare($sqlTarifa);
        $stmtTarifa->execute([':tipo' => $tipo_habitacion_elegida]);
        $tarifa = $stmtTarifa->fetch(PDO::FETCH_ASSOC);

        $id_tarifa = $tarifa['id_tarifa'] ?? 1;

        $sqlPolitica = "SELECT id_politica FROM politica_cancelacion WHERE vigente = 1 LIMIT 1";
        $stmtPolitica = $conexion->query($sqlPolitica);
        $politica = $stmtPolitica->fetch(PDO::FETCH_ASSOC);
        $id_politica = $politica['id_politica'] ?? 1;

        $sqlDisponibilidad = "SELECT COUNT(*) FROM reservacion WHERE numero_habitacion = :num_hab AND estado = 'Activa' AND NOT (:salida <= fecha_entrada OR :entrada >= fecha_salida)";
        $stmtDisp = $conexion->prepare($sqlDisponibilidad);
        $stmtDisp->execute([':num_hab' => $numero_habitacion, ':entrada' => $fecha_entrada, ':salida' => $fecha_salida]);
        
        if ($stmtDisp->fetchColumn() > 0) {
            $errorReserva = "Lo sentimos, la habitación acaba de ser ocupada en esas fechas.";
        } else {
            $sqlInsert = "INSERT INTO reservacion (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal, id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica) 
                          VALUES (:num_conf, :f_entrada, :f_salida, 'Activa', :notas, :canal, :id_huesped, :id_recep, :num_hab, :id_tarifa, :id_pol)";
            
            $stmtInsert = $conexion->prepare($sqlInsert);
            $stmtInsert->execute([
                ':num_conf' => $numero_confirmacion,
                ':f_entrada' => $fecha_entrada,
                ':f_salida' => $fecha_salida,
                ':notas' => $notas,
                ':canal' => $canal,
                ':id_huesped' => $id_huesped,
                ':id_recep' => $id_recepcionista,
                ':num_hab' => $numero_habitacion,
                ':id_tarifa' => $id_tarifa,
                ':id_pol' => $id_politica
            ]);

            $codigoExito = $numero_confirmacion;
        }
    } catch (Exception $e) {
        $errorReserva = "Error al procesar la reservación: " . $e->getMessage();
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
    <?php if (!empty($codigoExito)): ?>
      <div style="background: #fff; padding: 40px; border-radius: 8px; max-width: 600px; margin: 40px auto; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 50px; color: #667121; margin-bottom: 15px;"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="color: #102948; margin-bottom: 10px;">¡Reservación confirmada!</h1>
        <p style="color: #666; margin-bottom: 25px;">Gracias, <?php echo htmlspecialchars($_SESSION['nombre']); ?>. Te esperamos en Costa Azul.</p>
        <div style="background: #f9f6f0; border: 1px dashed #102948; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
          <span style="font-size: 12px; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px;">Tu número de confirmación</span>
          <strong style="font-size: 28px; color: #102948; letter-spacing: 2px;"><?php echo $codigoExito; ?></strong>
        </div>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <a href="Reser.php" style="background: #102948; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Hacer otra reserva</a>
          <a href="../Pagar/pagar.php?codigo=<?php echo $codigoExito; ?>" style="background: #667121; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none;">Pagar ahora</a>
        </div>
      </div>
    <?php else: ?>
      <section class="left-section">
        <h1>Reserva tu estancia</h1>
        <?php if (!empty($errorReserva)): ?>
          <div style="background: #f2dede; color: #a94442; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($errorReserva); ?>
          </div>
        <?php endif; ?>

        <form method="GET" action="" class="filter-box">
          <div class="date-input-group">
            <label>Entrada</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" name="entrada" value="<?php echo htmlspecialchars($fecha_entrada); ?>" required>
            </div>
          </div>
          <div class="date-input-group">
            <label>Salida</label>
            <div class="input-field">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" name="salida" value="<?php echo htmlspecialchars($fecha_salida); ?>" required>
            </div>
          </div>
          <button type="submit" class="btn-availability"><i class="fa-solid fa-magnifying-glass"></i> Buscar disponibilidad</button>
        </form>

        <div class="rooms-list">
          <form method="POST" action="" id="formReserva">
            <input type="hidden" name="fecha_entrada" value="<?php echo htmlspecialchars($fecha_entrada); ?>">
            <input type="hidden" name="fecha_salida" value="<?php echo htmlspecialchars($fecha_salida); ?>">
            <input type="hidden" name="tipo_habitacion" id="inputTipoHabitacion" value="Sencilla">

            <!-- Habitación Sencilla con su imagen -->
            <div class="room-card selected" id="cardSencilla" onclick="seleccionarHabitacion('Sencilla', this)">
              <img src="https://images.unsplash.com/photo-1591088398332-8a7791972843?q=80&w=600&auto=format&fit=crop" alt="Habitación sencilla">
              <div class="room-details">
                <h2>Habitación sencilla</h2>
                <span class="badge available">Disponible</span>
                <p><?php echo $noches; ?> noche(s) · $<span id="precioSencilla"><?php echo number_format($precio_sencilla, 2); ?></span> por noche</p>
              </div>
            </div>

            <!-- Habitación Doble con su imagen -->
            <div class="room-card" id="cardDoble" onclick="seleccionarHabitacion('Doble', this)">
              <img src="https://images.unsplash.com/photo-1566665797739-1674de7a421a?q=80&w=600&auto=format&fit=crop" alt="Habitación doble">
              <div class="room-details">
                <h2>Habitación doble</h2>
                <span class="badge available">Disponible</span>
                <p><?php echo $noches; ?> noche(s) · $<span id="precioDoble"><?php echo number_format($precio_doble, 2); ?></span> por noche</p>
              </div>
            </div>
          </form>
        </div>
      </section>

      <section class="right-section">
        <div class="summary-card">
          <h3>Tus datos y resumen</h3>
          <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Huésped: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></p>
          <div class="summary-details">
            <p>Estancia: <strong><?php echo $noches; ?> noche(s)</strong></p>
            <p>Del <?php echo htmlspecialchars($fecha_entrada); ?> al <?php echo htmlspecialchars($fecha_salida); ?></p>
            <p style="margin-top: 10px; font-size: 16px; color: #102948;">Habitación: <strong id="lblTipoSeleccionado">Sencilla</strong></p>
            <p style="font-size: 18px; font-weight: bold; color: #667121; margin-top: 5px;">Total estimado: $<span id="lblTotalCalculado"><?php echo number_format($noches * $precio_sencilla, 2); ?></span></p>
          </div>
          <div style="margin-top: 15px;">
            <label style="font-size: 13px; font-weight: 500;">Solicitudes especiales (opcional)</label>
            <textarea name="notas" form="formReserva" placeholder="Ej. Cama extra..." style="width: 100%; height: 70px; margin-top: 5px; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
          </div>
          <button type="submit" form="formReserva" style="width: 100%; margin-top: 20px; background: #102948; color: #fff; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Reservar ahora</button>
        </div>
      </section>
    <?php endif; ?>
  </main>
  <script>
    const precioSencilla = <?php echo $precio_sencilla; ?>;
    const precioDoble = <?php echo $precio_doble; ?>;
    const noches = <?php echo $noches; ?>;
    function seleccionarHabitacion(tipo, elementoCard) {
      document.getElementById('inputTipoHabitacion').value = tipo;
      document.querySelectorAll('.room-card').forEach(card => card.classList.remove('selected'));
      elementoCard.classList.add('selected');
      document.getElementById('lblTipoSeleccionado').innerText = tipo;
      let total = (tipo === 'Sencilla') ? (precioSencilla * noches) : (precioDoble * noches);
      document.getElementById('lblTotalCalculado').innerText = total.toFixed(2);
    }
  </script>
</body>
</html>