<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';

verificarAcceso('huesped');

$id_huesped = (int) $_SESSION['id_usuario'];
$codigo     = trim($_REQUEST['codigo'] ?? '');

$mensaje_exito = isset($_GET['ok']) ? "La reservación ha sido cancelada correctamente." : '';
$mensaje_error = '';

// CANCELAR (solo si la reservación es del huésped en sesión y sigue activa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conexion->prepare(
    "UPDATE reservacion
     SET estado = 'Cancelada', modificado_por = ?, fecha_modificacion = NOW()
     WHERE numero_confirmacion = ? AND id_huesped = ? AND estado = 'Activa'"
    );
    $stmt->execute([$id_huesped, $codigo, $id_huesped]);    


    if ($stmt->rowCount() === 1) {
        header("Location: Cance.php?codigo=" . urlencode($codigo) . "&ok=1");
        exit;
    }
    $mensaje_error = "No se pudo cancelar: la reservación no existe, no es tuya o ya estaba cancelada.";
}

// BUSCAR (solo entre las reservaciones del huésped en sesión)
$reservacion = null;
$costo_total = 0;

if ($codigo !== '') {
    $stmt = $conexion->prepare(
        "SELECT r.numero_confirmacion, r.estado, r.fecha_entrada, r.fecha_salida,
                r.numero_habitacion, t.precio_noche
         FROM reservacion r
         JOIN tarifa t ON r.id_tarifa = t.id_tarifa
         WHERE r.numero_confirmacion = ? AND r.id_huesped = ?"
    );
    $stmt->execute([$codigo, $id_huesped]);
    $reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reservacion) {
        $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / 86400);
        $costo_total = $noches * $reservacion['precio_noche'];
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
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="../Pagar/pagar.php">Pagar</a>
      <a href="Cance.php" class="active">Cancelar reservación</a>
    </nav>
  </header>

  <main class="main-container">
    <div class="cancel-card">
      <h1>Cancelar reservación</h1>
      <p class="subtitle">Escribe tu número de confirmación para encontrar tu reserva.</p>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo htmlspecialchars($mensaje_exito); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <form method="GET" class="search-form">
        <div class="input-group">
          <label for="codigo">Número de confirmación</label>
          <div class="input-field">
            <i class="fa-solid fa-ticket icon-left"></i>
            <input type="text" id="codigo" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($reservacion): ?>
        <div class="reservation-box">
          <div class="res-header">
            <h2>Reservación <span><?php echo htmlspecialchars($reservacion['numero_confirmacion']); ?></span></h2>
            <span class="<?php echo $cancelada ? 'badge-cancelled' : 'badge-active'; ?>"><?php echo htmlspecialchars($reservacion['estado']); ?></span>
          </div>
          <div class="res-details">
            <div class="detail-row"><span class="label">Huésped</span><span class="value"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span></div>
            <div class="detail-row"><span class="label">Fechas</span><span class="value"><?php echo htmlspecialchars($reservacion['fecha_entrada']); ?> al <?php echo htmlspecialchars($reservacion['fecha_salida']); ?></span></div>
            <div class="detail-row"><span class="label">Habitación</span><span class="value">Hab. <?php echo htmlspecialchars($reservacion['numero_habitacion']); ?></span></div>
            <div class="detail-row"><span class="label">Total</span><span class="value">$<?php echo number_format($costo_total, 2); ?></span></div>
          </div>
        </div>

        <div class="bottom-actions">
          <a href="../Reserva/Reser.php" class="btn-back">← Volver</a>
          <?php if ($cancelada): ?>
            <button type="button" class="btn-init-cancel" disabled style="background-color: #ccc; cursor: not-allowed;">Ya está cancelada</button>
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
      <h2>¿Cancelar esta reservación?</h2>
      <p>Su estado cambiará a "Cancelada".</p>
      <form method="POST">
        <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>">
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