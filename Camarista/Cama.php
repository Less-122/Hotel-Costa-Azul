<?php
session_start();

// Validación de seguridad por rol: Solo camaristas o administradores pueden acceder
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'camarista' && $_SESSION['rol'] !== 'admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../../config/conexion.php';

// Si se recibe una petición asíncrona (AJAX) para actualizar el estado de la habitación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_habitacion']) && isset($_POST['nuevo_estado'])) {
    $id_habitacion = $_POST['id_habitacion'];
    $nuevo_estado = $_POST['nuevo_estado']; // 'Limpia', 'Sucia', 'Mantenimiento'

    try {
        $stmt_update = $conexion->prepare("UPDATE habitaciones SET estado = ? WHERE id_habitacion = ? OR numero = ?");
        $stmt_update->execute([$nuevo_estado, $id_habitacion, $id_habitacion]);
        
        echo json_encode(['success' => true, 'mensaje' => "Habitación actualizada correctamente"]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Consultar todas las habitaciones asignadas o registradas en el hotel
try {
    $stmt_habs = $conexion->query("SELECT id_habitacion, numero, tipo, estado FROM habitaciones ORDER BY numero ASC");
    $habitaciones = $stmt_habs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $habitaciones = [];
}

// Contar cuántas habitaciones están sucias para el aviso de bienvenida
$sucias_count = 0;
foreach ($habitaciones as $h) {
    if (strtolower($h['estado']) === 'sucia') {
        $sucias_count++;
    }
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Elena R.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Panel de Camarista</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos -->
  <link rel="stylesheet" href="Cama.css">
</head>
<body>

  <!-- Navbar Superior de Camarista -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        <span class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['rol'] ?? 'Camarista')); ?></span>
      </div>
    </div>
  </header>

  <!-- Contenedor Principal -->
  <main class="main-container">
    
    <div class="welcome-area">
      <h1>Hola, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?></h1>
      <p id="subtitleNotice">Tienes <?php echo $sucias_count; ?> habitación<?php echo $sucias_count == 1 ? '' : 'es'; ?> sucia<?php echo $sucias_count == 1 ? '' : 's'; ?>.</p>
    </div>

    <!-- Lista de Habitaciones Asignadas Dinámica -->
    <div class="rooms-list" id="roomsListContainer">

      <?php if (count($habitaciones) > 0): ?>
        <?php foreach ($habitaciones as $index => $hab): ?>
          <?php 
            $num_hab = $hab['numero'] ?? $hab['id_habitacion'];
            $tipo_hab = $hab['tipo'] ?? 'Sencilla';
            $estado_hab = $hab['estado'] ?? 'Sucia'; // Ej: 'Limpia', 'Sucia', 'Mantenimiento'
            
            // Clases dinámicas según estado
            $is_dirty = (strtolower($estado_hab) === 'sucia');
            $is_clean = (strtolower($estado_hab) === 'limpia');
            $is_maint = (strtolower($estado_hab) === 'mantenimiento');
            
            $badge_class = $is_clean ? 'clean' : ($is_dirty ? 'dirty' : 'maint');
            $badge_text = ucfirst($estado_hab);
            
            // Por defecto abrimos la primera tarjeta, las demás contraídas
            $card_class = ($index === 0) ? 'room-card expanded' : 'room-card collapsed';
            $body_style = ($index === 0) ? '' : 'display: none;';
          ?>

          <div class="<?php echo $card_class; ?>" data-room="<?php echo $num_hab; ?>">
            <div class="room-card-header" onclick="toggleCard(this)">
              <div>
                <h2>Hab. <?php echo htmlspecialchars($num_hab); ?></h2>
                <span class="room-type"><?php echo htmlspecialchars($tipo_hab); ?></span>
              </div>
              <div class="header-right">
                <span class="badge <?php echo $badge_class; ?>" id="badge-<?php echo $num_hab; ?>"><?php echo $badge_text; ?></span>
                <?php if ($index !== 0): ?>
                  <span class="open-link">Abrir ›</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="room-card-body" style="<?php echo $body_style; ?>">
              <span class="change-state-label">Cambiar estado</span>
              <div class="state-buttons">
                <button type="button" class="state-btn clean-btn <?php echo $is_clean ? 'active' : ''; ?>" onclick="changeRoomState('<?php echo $num_hab; ?>', 'Limpia')">
                  <i class="fa-solid fa-check"></i> Limpia
                </button>
                <button type="button" class="state-btn dirty-btn <?php echo $is_dirty ? 'active' : ''; ?>" onclick="changeRoomState('<?php echo $num_hab; ?>', 'Sucia')">
                  Sucia
                </button>
              </div>
              <button type="button" class="state-btn maint-btn <?php echo $is_maint ? 'active' : ''; ?>" onclick="changeRoomState('<?php echo $num_hab; ?>', 'Mantenimiento')">
                Mantenimiento
              </button>
            </div>
          </div>

        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align: center; color: #666; padding: 20px;">No hay habitaciones registradas en el sistema.</p>
      <?php endif; ?>

    </div>

    <!-- Notificación Verde de Éxito -->
    <div id="successNotification" class="success-notification-box" style="display: none;">
      <div class="success-icon"><i class="fa-solid fa-check"></i></div>
      <div class="success-text">
        <b id="notificationTitle">Habitación actualizada</b>
        <span>Recepción ya puede verla.</span>
      </div>
    </div>

  </main>

  <!-- Script para interactuar con la Base de Datos vía fetch / AJAX -->
  <script>
    function toggleCard(headerElement) {
      const card = headerElement.closest('.room-card');
      const body = card.querySelector('.room-card-body');
      const openLink = card.querySelector('.open-link');
      
      if (card.classList.contains('collapsed')) {
        // Expandir
        card.classList.remove('collapsed');
        card.classList.add('expanded');
        body.style.display = 'block';
        if (openLink) openLink.style.display = 'none';
      } else {
        // Contraer
        card.classList.remove('expanded');
        card.classList.add('collapsed');
        body.style.display = 'none';
        if (openLink) openLink.style.display = 'inline';
      }
    }

    function changeRoomState(roomNumber, newState) {
      // Petición AJAX en segundo plano para actualizar la BD sin recargar
      const formData = new FormData();
      formData.append('id_habitacion', roomNumber);
      formData.append('nuevo_estado', newState);

      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Actualizar la interfaz visualmente
          const badge = document.getElementById('badge-' + roomNumber);
          badge.textContent = newState;
          
          // Cambiar clases del badge
          badge.className = 'badge';
          if (newState === 'Limpia') badge.classList.add('clean');
          else if (newState === 'Sucia') badge.classList.add('dirty');
          else badge.classList.add('maint');

          // Actualizar botones activos dentro de la tarjeta
          const card = document.querySelector(`[data-room="${roomNumber}"]`);
          const buttons = card.querySelectorAll('.state-btn');
          buttons.forEach(btn => btn.classList.remove('active'));

          if (newState === 'Limpia') card.querySelector('.clean-btn').classList.add('active');
          if (newState === 'Sucia') card.querySelector('.dirty-btn').classList.add('active');
          if (newState === 'Mantenimiento') card.querySelector('.maint-btn').classList.add('active');

          // Mostrar notificación flotante de éxito
          const notif = document.getElementById('successNotification');
          document.getElementById('notificationTitle').textContent = `Hab. ${roomNumber} marcada como ${newState}`;
          notif.style.display = 'flex';
          
          setTimeout(() => {
            notif.style.display = 'none';
          }, 3500);
        } else {
          alert('Error al actualizar el estado de la habitación.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    }
  </script>
</body>
</html>