<?php
session_start();

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Código buscado (por defecto o vía GET/POST)
$codigo_buscado = trim($_REQUEST['codigo'] ?? 'AX7B9');
$reservacion = null;

// 1. Procesar la acción de cancelación si se confirma en el modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ejecutar_cancelacion') {
    $codigo_a_cancelar = trim($_POST['codigo_reservacion'] ?? '');

    if (!empty($codigo_a_cancelar)) {
        try {
            // Actualizamos el estado de la reservación a 'Cancelada'
            $stmt_cancel = $conexion->prepare("UPDATE reservaciones SET estado = 'Cancelada' WHERE codigo = ? OR id_reservacion = ?");
            if ($stmt_cancel->execute([$codigo_a_cancelar, $codigo_a_cancelar])) {
                $mensaje_exito = "La reservación <strong>$codigo_a_cancelar</strong> ha sido cancelada exitosamente.";
                $codigo_buscado = $codigo_a_cancelar;
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al cancelar la reservación: " . $e->getMessage();
        }
    }
}

// 2. Buscar la reservación en la base de datos para mostrar sus detalles
try {
    $stmt_busqueda = $conexion->prepare("
        SELECT r.*, u.nombre AS nombre_huesped, u.apellido AS apellido_huesped, h.numero AS num_habitacion, h.tipo AS tipo_habitacion 
        FROM reservaciones r 
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario 
        LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion 
        WHERE r.codigo = ? OR r.id_reservacion = ?
    ");
    $stmt_busqueda->execute([$codigo_buscado, $codigo_buscado]);
    $reservacion = $stmt_busqueda->fetch(PDO::FETCH_ASSOC);
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

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos -->
  <link rel="stylesheet" href="Cance.css">
</head>
<body>

  <!-- Navbar Superior -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../vistas/huesped/catalogo.php">Reservar</a>
      <a href="../vistas/huesped/mis_reservas.php">Mis Reservas</a>
      <a href="Cance.php" class="active">Cancelar reservación</a>
    </nav>
  </header>

  <!-- Contenedor Principal -->
  <main class="main-container">
    
    <div class="cancel-card">
      
      <h1>Cancelar reservación</h1>
      <p class="subtitle">Escribe tu número de confirmación para encontrar tu reserva.</p>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold; text-align: center;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold; text-align: center;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <!-- Formulario / Buscador -->
      <form method="GET" action="" class="search-form">
        <div class="input-group">
          <label for="confCode">Número de confirmación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket icon-left"></i>
            <input type="text" id="confCode" name="codigo" value="<?php echo htmlspecialchars($codigo_buscado); ?>" placeholder="Ej. AX7B9" required>
          </div>
        </div>

        <button type="submit" class="btn-search">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </form>

      <?php if ($reservacion): ?>
        <?php 
          $estado_actual = $reservacion['estado'] ?? 'Activa';
          $badge_class = (strtolower($estado_actual) === 'cancelada') ? 'badge-cancelled' : 'badge-active';
          $nombre_completo = trim(($reservacion['nombre_huesped'] ?? '') . ' ' . ($reservacion['apellido_huesped'] ?? '')) ?: ($reservacion['nombre_cliente'] ?? 'Huésped');
          $num_hab = $reservacion['num_habitacion'] ?? '204';
          $tipo_hab = $reservacion['tipo_habitacion'] ?? 'Sencilla';
          $total_res = $reservacion['total'] ?? 2550.00;
        ?>
        <!-- Detalle de la Reservación -->
        <div id="reservationResult" class="reservation-box">
          
          <div class="res-header">
            <h2>Reservación <span id="displayCode"><?php echo htmlspecialchars($reservacion['codigo']); ?></span></h2>
            <span id="statusBadge" class="<?php echo $badge_class; ?>"><?php echo htmlspecialchars($estado_actual); ?></span>
          </div>

          <div class="res-details">
            <div class="detail-row">
              <span class="label">Huésped</span>
              <span class="value"><?php echo htmlspecialchars($nombre_completo); ?></span>
            </div>
            <div class="detail-row">
              <span class="label">Fechas</span>
              <span class="value">
                <?php echo date('d M Y', strtotime($reservacion['fecha_entrada'])); ?> al <?php echo date('d M Y', strtotime($reservacion['fecha_salida'])); ?>
              </span>
            </div>
            <div class="detail-row">
              <span class="label">Habitación</span>
              <span class="value"><?php echo htmlspecialchars($num_hab); ?> · <?php echo htmlspecialchars($tipo_hab); ?></span>
            </div>
            <div class="detail-row">
              <span class="label">Total</span>
              <span class="value">$<?php echo number_format($total_res, 2); ?></span>
            </div>
          </div>

        </div>

        <p class="policy-text">Revisa la política de cancelación vigente antes de continuar.</p>

        <!-- Botones Inferiores -->
        <div class="bottom-actions">
          <a href="../index.php" class="btn-back">← Volver</a>
          <?php if (strtolower($estado_actual) !== 'cancelada'): ?>
            <button type="button" id="openModalBtn" class="btn-init-cancel" onclick="abrirModalCancelacion()">Cancelar reservación</button>
          <?php else: ?>
            <button type="button" class="btn-init-cancel" disabled style="background-color: #ccc; cursor: not-allowed;">Ya está cancelada</button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No se encontró ninguna reservación con ese número de confirmación.</p>
      <?php endif; ?>

    </div>

  </main>

  <!-- Ventana Emergente (Modal de Confirmación de Cancelación) -->
  <div id="confirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <div class="modal-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>
      <h2>¿Cancelar esta reservación?</h2>
      <p>Su estado cambiará a "Cancelada".</p>
      
      <form method="POST" action="">
        <input type="hidden" name="accion" value="ejecutar_cancelacion">
        <input type="hidden" name="codigo_reservacion" value="<?php echo htmlspecialchars($codigo_buscado); ?>">
        
        <div class="modal-buttons">
          <button type="button" id="btnNoReturn" class="btn-no" onclick="cerrarModalCancelacion()">No, volver</button>
          <button type="submit" id="btnYesCancel" class="btn-yes">Sí, cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Script para controlar el modal -->
  <script>
    function abrirModalCancelacion() {
      document.getElementById('confirmModal').style.display = 'flex';
    }

    function cerrarModalCancelacion() {
      document.getElementById('confirmModal').style.display = 'none';
    }
  </script>
</body>
</html>