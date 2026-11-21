<?php
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../config/reservaciones.php';

verificarAcceso('huesped');

$id_huesped    = (int) $_SESSION['id_usuario'];
$reservaciones = [];
$mensaje_error = '';

try {
    $reservaciones = reservacionesDelHuesped($conexion, $id_huesped);
} catch (PDOException $e) {
    error_log('MisReservas - listar: ' . $e->getMessage());
    $mensaje_error = 'No se pudieron cargar tus reservaciones. Inténtalo de nuevo.';
}

$hoy = date('Y-m-d');
$clases_estado = [
    'Activa'     => 'badge-active',
    'En curso'   => 'badge-progress',
    'Finalizada' => 'badge-done',
    'Cancelada'  => 'badge-cancelled',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Mis reservaciones</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="misreservas.css">
</head>
<body>
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.php">Reservar</a>
      <a href="misreservas.php" class="active">Mis reservaciones</a>
      <a href="../Pagar/pagar.php">Pagar</a>
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
    <div class="list-card">
      <h1>Mis reservaciones</h1>
      <p class="subtitle">Aquí encuentras tu código de confirmación y puedes pagar o cancelar sin necesidad de anotarlo.</p>

      <?php if ($mensaje_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php foreach ($reservaciones as $r): ?>
        <?php
          $abierta = in_array($r['estado'], ESTADOS_ABIERTOS, true);
          $clase   = $clases_estado[$r['estado']] ?? 'badge-done';
        ?>
        <div class="res-item">
          <div class="res-main">
            <div class="res-top">
              <span class="res-code"><?php echo htmlspecialchars($r['numero_confirmacion'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="<?php echo $clase; ?>"><?php echo htmlspecialchars($r['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="res-meta">
              <?php echo fecha($r['fecha_entrada'], true); ?> → <?php echo fecha($r['fecha_salida'], true); ?>
              · Hab. <?php echo htmlspecialchars($r['numero_habitacion'], ENT_QUOTES, 'UTF-8'); ?>
              (<?php echo htmlspecialchars($r['tipo_habitacion'], ENT_QUOTES, 'UTF-8'); ?>)
            </div>
            <div class="res-money">
              Total <b>$<?php echo number_format($r['costo'], 2); ?></b> ·
              Pagado <b>$<?php echo number_format($r['pagado'], 2); ?></b>
              <?php if ($abierta): ?>
                · Saldo <b class="<?php echo $r['saldo'] > 0 ? 'balance-due' : 'balance-ok'; ?>">$<?php echo number_format($r['saldo'], 2); ?></b>
              <?php endif; ?>
            </div>
          </div>
          <div class="res-actions">
            <?php if ($abierta && $r['saldo'] > 0): ?>
              <a class="btn-pay" href="../Pagar/pagar.php?codigo=<?php echo urlencode($r['numero_confirmacion']); ?>">
                <i class="fa-solid fa-credit-card"></i> Pagar
              </a>
            <?php endif; ?>
            <?php if ($r['estado'] === 'Activa' && $r['fecha_entrada'] >= $hoy): ?>
              <a class="btn-cancel-link" href="../Cancelar/Cance.php?codigo=<?php echo urlencode($r['numero_confirmacion']); ?>">Cancelar</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$reservaciones && !$mensaje_error): ?>
        <div class="empty-state">
          Todavía no tienes reservaciones.<br>
          <a href="../Reserva/Reser.php">Haz tu primera reservación</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
