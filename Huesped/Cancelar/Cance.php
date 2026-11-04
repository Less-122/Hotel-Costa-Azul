<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Huesped') {
    header("Location: ../Login_regis/index.php?error=acceso_denegado");
    exit();
}

$mensaje_exito = '';
$mensaje_error = '';
$codigo_buscado = trim($_REQUEST['codigo'] ?? 'AX7B9');
$reservacion = null;
$costo_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ejecutar_cancelacion') {
    $codigo_a_cancelar = trim($_POST['codigo_reservacion'] ?? '');
    if (!empty($codigo_a_cancelar)) {
        try {
            $stmt_cancel = $conexion->prepare("UPDATE reservacion SET estado = 'Cancelada' WHERE numero_confirmacion = ? OR id_reservacion = ?");
            if ($stmt_cancel->execute([$codigo_a_cancelar, $codigo_a_cancelar])) {
                $mensaje_exito = "La reservación <strong>$codigo_a_cancelar</strong> ha sido cancelada exitosamente.";
                $codigo_buscado = $codigo_a_cancelar;
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al cancelar la reservación: " . $e->getMessage();
        }
    }
}

try {
    $stmt_busqueda = $conexion->prepare("
        SELECT r.*, u.nombre AS nombre_huesped, h.tipo_habitacion, t.precio_noche 
        FROM reservacion r 
        LEFT JOIN usuario u ON r.id_huesped = u.id_usuario 
        LEFT JOIN habitacion h ON r.numero_habitacion = h.numero_habitacion 
        LEFT JOIN tarifa t ON r.id_tarifa = t.id_tarifa
        WHERE r.numero_confirmacion = ? OR r.id_reservacion = ?
    ");
    $stmt_busqueda->execute([$codigo_buscado, $codigo_buscado]);
    $reservacion = $stmt_busqueda->fetch(PDO::FETCH_ASSOC);

    if ($reservacion) {
        $noches = max(1, (strtotime($reservacion['fecha_salida']) - strtotime($reservacion['fecha_entrada'])) / (60 * 60 * 24));
        $precio_noche = floatval($reservacion['precio_noche'] ?? 850.00);
        $costo_total = $noches * $precio_noche;
    }
} catch (PDOException $e) {
    $reservacion = null;
    $mensaje_error = "Error al consultar la base de datos.";
}
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

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: #3c763d; background: #dff0d8; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: #a94442; background: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: 500; text-align: center;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label>Número de confirmación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket icon-left"></i>
            <input type="text" name="codigo" value="<?php echo htmlspecialchars($codigo_buscado); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
      </form>

      <?php if ($reservacion): ?>
        <?php 
          $estado_actual = $reservacion['estado'] ?? 'Activa';
          $badge_class = (strtolower($estado_actual) === 'cancelada') ? 'badge-cancelled' : 'badge-active';
        ?>
        <div class="reservation-box">
          <div class="res-header">
            <h2>Reservación <span><?php echo htmlspecialchars($reservacion['numero_confirmacion']); ?></span></h2>
            <span class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars($estado_actual); ?></span>
          </div>
          <div class="res-details">
            <div class="detail-row"><span class="label">Huésped</span><span class="value"><?php echo htmlspecialchars($reservacion['nombre_huesped']); ?></span></div>
            <div class="detail-row"><span class="label">Fechas</span><span class="value"><?php echo $reservacion['fecha_entrada']; ?> al <?php echo $reservacion['fecha_salida']; ?></span></div>
            <div class="detail-row"><span class="label">Habitación</span><span class="value">Hab. <?php echo $reservacion['numero_habitacion']; ?></span></div>
            <div class="detail-row"><span class="label">Total</span><span class="value">$<?php echo number_format($costo_total, 2); ?></span></div>
          </div>
        </div>

        <div class="bottom-actions">
          <a href="../Reserva/Reser.php" class="btn-back">← Volver</a>
          <?php if (strtolower($estado_actual) !== 'cancelada'): ?>
            <button type="button" class="btn-init-cancel" onclick="abrirModalCancelacion()">Cancelar reservación</button>
          <?php else: ?>
            <button type="button" class="btn-init-cancel" disabled style="background-color: #ccc; cursor: not-allowed;">Ya está cancelada</button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No se encontró ninguna reservación con ese número.</p>
      <?php endif; ?>
    </div>
  </main>

  <div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <h2>¿Cancelar esta reservación?</h2>
      <p>Su estado cambiará a "Cancelada".</p>
      <form method="POST" action="">
        <input type="hidden" name="accion" value="ejecutar_cancelacion">
        <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">
        <div class="modal-buttons">
          <button type="button" class="btn-no" onclick="cerrarModalCancelacion()">No, volver</button>
          <button type="submit" class="btn-yes">Sí, cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function abrirModalCancelacion() { document.getElementById('confirmModal').style.display = 'flex'; }
    function cerrarModalCancelacion() { document.getElementById('confirmModal').style.display = 'none'; }
  </script>
</body>
</html>