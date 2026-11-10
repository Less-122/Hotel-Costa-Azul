<?php
require_once '../../config/sesion.php';
verificarAcceso(['recepcionista', 'admin']);   // solo recepción (o admin)

require_once '../../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

$limpieza_validas = ['Limpia', 'Sucia', 'Mantenimiento'];
$disp_validas     = ['Libre', 'Ocupada'];

// Aviso tras guardar (se redirige después del POST para que F5 no reenvíe el formulario)
if (($_GET['ok'] ?? '') === '1' && preg_match('/^[0-9A-Za-z]{1,5}$/', $_GET['hab'] ?? '')) {
    $mensaje_exito = "¡Habitación " . $_GET['hab'] . " actualizada con éxito!";
    if (($_GET['mant'] ?? '') === '1') {
        $mensaje_exito .= " En mantenimiento la habitación queda como no disponible.";
    }
}

// Actualizar limpieza y disponibilidad de una habitación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_habitacion') {
    $numero   = trim($_POST['id_habitacion'] ?? '');
    $limpieza = $_POST['cleanStatus'] ?? '';
    $disp     = $_POST['dispStatus'] ?? '';

    if ($numero === '' || !in_array($limpieza, $limpieza_validas, true) || !in_array($disp, $disp_validas, true)) {
        $mensaje_error = "Datos no válidos para actualizar la habitación.";
    } else {
        // En la BD, disponible = 1 (Libre) / 0 (Ocupada). Una habitación en mantenimiento nunca queda libre.
        $forzada_mant = ($limpieza === 'Mantenimiento' && $disp === 'Libre');
        $disponible   = ($disp === 'Libre' && $limpieza !== 'Mantenimiento') ? 1 : 0;

        try {
            $existe = $conexion->prepare("SELECT COUNT(*) FROM habitacion WHERE numero_habitacion = ?");
            $existe->execute([$numero]);
            if ((int) $existe->fetchColumn() === 0) {
                $mensaje_error = "La habitación no existe.";
            } else {
                $conexion->prepare("UPDATE habitacion SET estado_limpieza = ?, disponible = ? WHERE numero_habitacion = ?")
                         ->execute([$limpieza, $disponible, $numero]);
                header("Location: hab.php?ok=1&hab=" . urlencode($numero) . ($forzada_mant ? "&mant=1" : ""));
                exit();
            }
        } catch (PDOException $e) {
            error_log('Recepción - actualizar habitación: ' . $e->getMessage());
            $mensaje_error = "No se pudo actualizar la habitación.";
        }
    }
}

// Consultar todas las habitaciones
try {
    $habitaciones = $conexion->query(
        "SELECT numero_habitacion AS numero, tipo_habitacion AS tipo, estado_limpieza, disponible
         FROM habitacion ORDER BY numero_habitacion ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Recepción - listar habitaciones: ' . $e->getMessage());
    $habitaciones = [];
    $mensaje_error = "Error al cargar el listado de habitaciones.";
}

// Contadores (KPIs inferiores)
$total_libres = $total_ocupadas = $total_sucias = $total_mantenimiento = 0;
foreach ($habitaciones as $h) {
    $es_mant = (strtolower($h['estado_limpieza']) === 'mantenimiento');
    if ($es_mant) {
        $total_mantenimiento++;
    } elseif ((int) $h['disponible'] === 1) {
        $total_libres++;
    } else {
        $total_ocupadas++;
    }
    if (strtolower($h['estado_limpieza']) === 'sucia') {
        $total_sucias++;
    }
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Recepción';
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
      <a href="../Recepcion/recep.php">Reservaciones</a>
      <a href="hab.php" class="active">Habitaciones</a>
      <a href="../Pagos/pagos.php">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
        <span class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></span>
      </div>
      <a href="../../config/logout.php" title="Cerrar sesión" style="margin-left: 14px; color: inherit;"><i class="fa-solid fa-right-from-bracket"></i></a>
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
                  $num = $hab['numero'];
                  $tipo = $hab['tipo'];
                  $limpieza = $hab['estado_limpieza'];
                  $es_mant = (strtolower($limpieza) === 'mantenimiento');
                  $disp = ((int) $hab['disponible'] === 1) ? 'Libre' : 'Ocupada';   // valor del formulario
                  
                  // Clases CSS y etiquetas
                  $badge_clean_class = (strtolower($limpieza) === 'limpia') ? 'clean' : ((strtolower($limpieza) === 'sucia') ? 'dirty' : 'maintenance');
                  $badge_disp_class = $es_mant ? 'na' : (($disp === 'Libre') ? 'free' : 'occupied');
                  $disp_texto = $es_mant ? 'No disponible' : $disp;
                  $is_selected = ($index === 0) ? 'selected' : '';

                  // Etiquetas para los filtros
                  $tags = [];
                  if ($es_mant) { $tags[] = 'mantenimiento'; }
                  elseif ($disp === 'Libre') { $tags[] = 'libres'; }
                  else { $tags[] = 'ocupadas'; }
                  if (strtolower($limpieza) === 'sucia') { $tags[] = 'sucias'; }
                ?>
                <tr class="room-row <?php echo $is_selected; ?>" data-room="<?php echo htmlspecialchars($num, ENT_QUOTES); ?>" data-type="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>" data-clean="<?php echo htmlspecialchars($limpieza, ENT_QUOTES); ?>" data-disp="<?php echo $disp; ?>" data-tags="<?php echo implode(' ', $tags); ?>" onclick="seleccionarHabitacion(this)">
                  <td><b><?php echo htmlspecialchars($num); ?></b></td>
                  <td><?php echo htmlspecialchars($tipo); ?></td>
                  <td><span class="badge <?php echo $badge_clean_class; ?>"><?php echo htmlspecialchars($limpieza); ?></span></td>
                  <td><span class="badge <?php echo $badge_disp_class; ?>"><?php echo $disp_texto; ?></span></td>
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
          <h2 id="selectedRoomTitle">Habitación <?php echo htmlspecialchars($habitaciones[0]['numero'] ?? ''); ?></h2>
          <span id="selectedRoomType" class="room-type-subtitle"><?php echo htmlspecialchars($habitaciones[0]['tipo'] ?? ''); ?></span>
        </div>

        <form id="roomUpdateForm" method="POST" action="">
          <input type="hidden" name="accion" value="actualizar_habitacion">
          <input type="hidden" id="inputRoomId" name="id_habitacion" value="<?php echo htmlspecialchars($habitaciones[0]['numero'] ?? ''); ?>">
          
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

    // Filtros (Todas / Libres / Ocupadas / Sucias / Mantenimiento)
    document.querySelectorAll('.filter-pills .pill').forEach(pill => {
      pill.addEventListener('click', () => {
        document.querySelectorAll('.filter-pills .pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        const filtro = pill.getAttribute('data-filter');
        document.querySelectorAll('#roomsTableBody tr.room-row').forEach(row => {
          const tags = (row.getAttribute('data-tags') || '').split(' ');
          row.style.display = (filtro === 'todas' || tags.includes(filtro)) ? '' : 'none';
        });
      });
    });

    // Al abrir, el panel derecho muestra los datos reales de la primera habitación
    const primeraFila = document.querySelector('.room-row.selected');
    if (primeraFila) seleccionarHabitacion(primeraFila);
  </script>
</body>
</html>