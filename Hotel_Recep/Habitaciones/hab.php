<?php
session_start();

// Validación de seguridad por rol: Solo recepción o administradores pueden acceder
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'recepcion' && $_SESSION['rol'] !== 'admin')) {
    header("Location: ../auth/login.php");
    exit();
}

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Procesar el formulario cuando se actualiza una habitación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_habitacion') {
    $id_habitacion = $_POST['id_habitacion'] ?? '';
    $nuevo_estado_limpieza = $_POST['cleanStatus'] ?? '';
    $nueva_disponibilidad = $_POST['dispStatus'] ?? '';

    if (!empty($id_habitacion)) {
        try {
            // Actualizamos limpieza y disponibilidad en la base de datos
            $stmt_update = $conexion->prepare("UPDATE habitaciones SET estado_limpieza = ?, disponibilidad = ? WHERE id_habitacion = ? OR numero = ?");
            $stmt_update->execute([$nuevo_estado_limpieza, $nueva_disponibilidad, $id_habitacion, $id_habitacion]);
            $mensaje_exito = "¡Habitación $id_habitacion actualizada con éxito!";
        } catch (PDOException $e) {
            $mensaje_error = "Error al actualizar la habitación: " . $e->getMessage();
        }
    }
}

// Consultar todas las habitaciones de la base de datos
try {
    $stmt_habs = $conexion->query("SELECT id_habitacion, numero, tipo, estado_limpieza, disponibilidad FROM habitaciones ORDER BY numero ASC");
    $habitaciones = $stmt_habs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $habitaciones = [];
    $mensaje_error = "Error al cargar el listado de habitaciones.";
}

// Calcular contadores para las estadísticas (KPIs inferiores)
$total_libres = 0;
$total_ocupadas = 0;
$total_sucias = 0;
$total_mantenimiento = 0;

foreach ($habitaciones as $h) {
    $limpieza = strtolower($h['estado_limpieza'] ?? '');
    $disp = strtolower($h['disponibilidad'] ?? '');

    if ($limpieza === 'sucia') {
        $total_sucias++;
    } elseif ($limpieza === 'mantenimiento') {
        $total_mantenimiento++;
    }

    if ($disp === 'libre') {
        $total_libres++;
    } elseif ($disp === 'ocupada') {
        $total_ocupadas++;
    }
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Marta Pérez';
$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Recepcionista');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Gestión de Habitaciones</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos -->
  <link rel="stylesheet" href="hab.css">
</head>
<body>

  <!-- Navbar Superior de Recepción -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="recep.php">Reservaciones</a>
      <a href="hab.php" class="active">Habitaciones</a>
      <a href="pagos.php">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        <span class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></span>
      </div>
    </div>
  </header>

  <!-- Contenedor Principal -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Tabla y Filtros -->
    <section class="left-column">
      
      <div class="header-title-area">
        <h1>Habitaciones</h1>
        <p>Hoy · <?php echo date('d M Y'); ?> · estado de limpieza y disponibilidad en tiempo real</p>
      </div>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <!-- Filtros de Pestañas -->
      <div class="filter-toolbar">
        <div class="filter-pills">
          <button class="pill active" data-filter="todas">Todas</button>
          <button class="pill" data-filter="libres">Libres</button>
          <button class="pill" data-filter="ocupadas">Ocupadas</button>
          <button class="pill" data-filter="sucias">Sucias</button>
          <button class="pill" data-filter="mantenimiento">Mantenimiento</button>
        </div>
      </div>

      <!-- Tabla de Habitaciones -->
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>HABITACIÓN</th>
              <th>TIPO</th>
              <th>LIMPIEZA</th>
              <th>DISPONIBILIDAD</th>
            </tr>
          </thead>
          <tbody id="roomsTableBody">
            <?php if (count($habitaciones) > 0): ?>
              <?php foreach ($habitaciones as $index => $hab): ?>
                <?php 
                  $num = $hab['numero'] ?? $hab['id_habitacion'];
                  $tipo = $hab['tipo'] ?? 'Sencilla';
                  $limpieza = $hab['estado_limpieza'] ?? 'Limpia';
                  $disp = $hab['disponibilidad'] ?? 'Libre';
                  
                  // Clases CSS para badges
                  $badge_clean_class = (strtolower($limpieza) === 'limpia') ? 'clean' : ((strtolower($limpieza) === 'sucia') ? 'dirty' : 'maintenance');
                  $badge_disp_class = (strtolower($disp) === 'libre') ? 'free' : ((strtolower($disp) === 'ocupada') ? 'occupied' : 'na');
                  $is_selected = ($index === 0) ? 'selected' : '';
                ?>
                <tr class="room-row <?php echo $is_selected; ?>" data-room="<?php echo $num; ?>" data-type="<?php echo $tipo; ?>" data-clean="<?php echo $limpieza; ?>" data-disp="<?php echo $disp; ?>" onclick="seleccionarHabitacion(this)">
                  <td><b><?php echo htmlspecialchars($num); ?></b></td>
                  <td><?php echo htmlspecialchars($tipo); ?></td>
                  <td><span class="badge <?php echo $badge_clean_class; ?>"><?php echo htmlspecialchars($limpieza); ?></span></td>
                  <td><span class="badge <?php echo $badge_disp_class; ?>"><?php echo htmlspecialchars($disp); ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" style="text-align: center; padding: 20px;">No hay habitaciones registradas en la base de datos.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="table-hint">Toca una fila para ver o cambiar su estado.</span>
          <span class="pagination-info"><?php echo count($habitaciones); ?> habitaciones en total</span>
        </div>
      </div>

      <!-- Tarjetas de Estadísticas Inferiores -->
      <div class="stats-grid">
        <div class="stat-card border-green">
          <span class="stat-number"><?php echo $total_libres; ?></span>
          <span class="stat-label">Libres</span>
        </div>
        <div class="stat-card border-red">
          <span class="stat-number"><?php echo $total_ocupadas; ?></span>
          <span class="stat-label">Ocupadas</span>
        </div>
        <div class="stat-card border-yellow">
          <span class="stat-number"><?php echo $total_sucias; ?></span>
          <span class="stat-label">Sucias</span>
        </div>
        <div class="stat-card border-gray">
          <span class="stat-number"><?php echo $total_mantenimiento; ?></span>
          <span class="stat-label">Mantenimiento</span>
        </div>
      </div>

    </section>

    <!-- Columna Derecha: Panel de Modificación de Habitación Seleccionada -->
    <aside class="right-column">
      <div class="room-edit-box">
        
        <div class="edit-header">
          <h2 id="selectedRoomTitle">Habitación <?php echo $habitaciones[0]['numero'] ?? '101'; ?></h2>
          <span id="selectedRoomType" class="room-type-subtitle"><?php echo $habitaciones[0]['tipo'] ?? 'Sencilla'; ?></span>
        </div>

        <form id="roomUpdateForm" method="POST" action="">
          <input type="hidden" name="accion" value="actualizar_habitacion">
          <input type="hidden" id="inputRoomId" name="id_habitacion" value="<?php echo $habitaciones[0]['numero'] ?? '101'; ?>">
          
          <div class="section-label">Estado de limpieza</div>
          <div class="cleaning-options">
            <label class="radio-option">
              <input type="radio" name="cleanStatus" value="Limpia">
              <span class="radio-box">Limpia</span>
            </label>
            <label class="radio-option active">
              <input type="radio" name="cleanStatus" value="Sucia" checked>
              <span class="radio-box">Sucia</span>
            </label>
            <label class="radio-option">
              <input type="radio" name="cleanStatus" value="Mantenimiento">
              <span class="radio-box">Mantenimiento</span>
            </label>
          </div>

          <div class="section-label">Disponibilidad</div>
          <div class="availability-options">
            <label class="radio-option active">
              <input type="radio" name="dispStatus" value="Libre" checked>
              <span class="radio-box">Libre</span>
            </label>
            <label class="radio-option">
              <input type="radio" name="dispStatus" value="Ocupada">
              <span class="radio-box">Ocupada</span>
            </label>
          </div>

          <button type="submit" class="btn-save-changes">Guardar cambios</button>

          <p class="sync-note">El cambio lo ve también la camarista en su lista.</p>

        </form>

      </div>
    </aside>

  </main>

  <!-- Script para interactuar con la selección de la tabla -->
  <script>
    function seleccionarHabitacion(row) {
      // Quitar la clase selected de todas las filas
      document.querySelectorAll('.room-row').forEach(tr => tr.classList.remove('selected'));
      row.classList.add('selected');

      const roomNum = row.getAttribute('data-room');
      const roomType = row.getAttribute('data-type');
      const cleanState = row.getAttribute('data-clean');
      const dispState = row.getAttribute('data-disp');

      // Actualizar título y subtítulo del panel derecho
      document.getElementById('selectedRoomTitle').textContent = `Habitación ${roomNum}`;
      document.getElementById('selectedRoomType').textContent = roomType;
      document.getElementById('inputRoomId').value = roomNum;

      // Marcar radios de limpieza
      document.querySelectorAll('input[name="cleanStatus"]').forEach(radio => {
        if (radio.value === cleanState) {
          radio.checked = true;
          radio.closest('.radio-option').classList.add('active');
        } else {
          radio.checked = false;
          radio.closest('.radio-option').classList.remove('active');
        }
      });

      // Marcar radios de disponibilidad
      document.querySelectorAll('input[name="dispStatus"]').forEach(radio => {
        if (radio.value === dispState) {
          radio.checked = true;
          radio.closest('.radio-option').classList.add('active');
        } else {
          radio.checked = false;
          radio.closest('.radio-option').classList.remove('active');
        }
      });
    }
  </script>
</body>
</html>