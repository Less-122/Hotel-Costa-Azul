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

// 1. Procesar la creación de una nueva reservación desde Recepción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'nueva_reservacion') {
    $nombre_huesped = trim($_POST['nombre_huesped'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_entrada = $_POST['fecha_entrada'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $tipo_habitacion = $_POST['tipo_habitacion'] ?? 'Sencilla';
    $notas = trim($_POST['notas'] ?? '');
    
    // Generar un código único de reservación de 5 caracteres en mayúsculas (ej: AX7B9)
    $codigo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
    $canal = 'Recepción';
    $estado = 'Activa';
    $id_usuario_registro = $_SESSION['id_usuario'] ?? null;

    if (!empty($nombre_huesped) && !empty($fecha_entrada) && !empty($fecha_salida)) {
        try {
            $stmt_insert = $conexion->prepare("
                INSERT INTO reservaciones (codigo, nombre_huesped, telefono, fecha_entrada, fecha_salida, tipo_habitacion, canal, estado, notas, registrado_por, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt_insert->execute([$codigo, $nombre_huesped, $telefono, $fecha_entrada, $fecha_salida, $tipo_habitacion, $canal, $estado, $notas, $id_usuario_registro])) {
                $mensaje_exito = "¡Reservación creada con éxito! Código asignado: <strong>$codigo</strong>";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al guardar la reservación: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor completa todos los campos obligatorios.";
    }
}

// 2. Obtener lista de reservaciones de la base de datos
try {
    $stmt_res = $conexion->query("
        SELECT id_reservacion, codigo, nombre_huesped, fecha_entrada, fecha_salida, tipo_habitacion, canal, estado, notas 
        FROM reservaciones 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $reservaciones = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservaciones = [];
}

// 3. Obtener contadores en tiempo real de las habitaciones
$stat_libres = 0;
$stat_ocupadas = 0;
$stat_sucias = 0;
$stat_mantenimiento = 0;

try {
    $stmt_hab_stats = $conexion->query("SELECT disponibilidad, estado_limpieza FROM habitaciones");
    $habs_data = $stmt_hab_stats->fetchAll(PDO::FETCH_ASSOC);

    foreach ($habs_data as $h) {
        $disp = strtolower($h['disponibilidad'] ?? '');
        $limp = strtolower($h['estado_limpieza'] ?? '');

        if ($limp === 'sucia') {
            $stat_sucias++;
        } elseif ($limp === 'mantenimiento') {
            $stat_mantenimiento++;
        }

        if ($disp === 'libre') {
            $stat_libres++;
        } elseif ($disp === 'ocupada') {
            $stat_ocupadas++;
        }
    }
} catch (PDOException $e) {
    // Si la tabla habitaciones no está presente o varía, se muestran valores neutros
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Marta Pérez';
$rol_usuario = ucfirst($_SESSION['rol'] ?? 'Recepcionista');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Panel de Recepción</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos -->
  <link rel="stylesheet" href="recep.css">
</head>
<body>

  <!-- Navbar Superior de Recepción -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="recep.php" class="active">Reservaciones</a>
      <a href="../Habitaciones/hab.php">Habitaciones</a>
      <a href="../Pagos/pagos.php">Pagos</a>
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
    
    <!-- Columna Izquierda: Tabla y Estadísticas -->
    <section class="left-column">
      
      <div class="header-title-area">
        <h1>Reservaciones</h1>
        <p>Hoy · <?php echo date('d M Y'); ?> · incluye reservas en línea y en recepción</p>
      </div>

      <?php if (!empty($mensaje_exito)): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_exito; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensaje_error)): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo $mensaje_error; ?></div>
      <?php endif; ?>

      <!-- Filtros y Buscador -->
      <div class="filter-toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" placeholder="Buscar por nombre o código" onkeyup="filtrarReservaciones()">
        </div>
        <div class="filter-pills">
          <button class="pill active" onclick="filtrarPorEstado('todas', this)">Todas</button>
          <button class="pill" onclick="filtrarPorEstado('activa', this)">Activas</button>
          <button class="pill" onclick="filtrarPorEstado('cancelada', this)">Canceladas</button>
        </div>
      </div>

      <!-- Tabla de Reservaciones Dinámica -->
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>CÓDIGO</th>
              <th>HUÉSPED</th>
              <th>ENTRADA</th>
              <th>SALIDA</th>
              <th>TIPO</th>
              <th>CANAL</th>
              <th>ESTADO</th>
              <th>NOTAS</th>
            </tr>
          </thead>
          <tbody id="reservationsTableBody">
            <?php if (count($reservaciones) > 0): ?>
              <?php foreach ($reservaciones as $res): ?>
                <?php 
                  $estado_class = 'active';
                  $estado_raw = strtolower($res['estado']);
                  if ($estado_raw === 'cancelada') $estado_class = 'cancelled';
                  elseif ($estado_raw === 'finalizada') $estado_class = 'finalized';
                ?>
                <tr class="res-row" data-estado="<?php echo $estado_raw; ?>">
                  <td><b><?php echo htmlspecialchars($res['codigo']); ?></b></td>
                  <td class="guest-cell"><?php echo htmlspecialchars($res['nombre_huesped']); ?></td>
                  <td><?php echo date('d M', strtotime($res['fecha_entrada'])); ?></td>
                  <td><?php echo date('d M', strtotime($res['fecha_salida'])); ?></td>
                  <td><?php echo htmlspecialchars($res['tipo_habitacion']); ?></td>
                  <td><?php echo htmlspecialchars($res['canal']); ?></td>
                  <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo htmlspecialchars($res['estado']); ?></span></td>
                  <td>
                    <?php if (!empty($res['notas'])): ?>
                      <i class="fa-regular fa-note-sticky note-icon" title="<?php echo htmlspecialchars($res['notas']); ?>"></i>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">No hay reservaciones registradas en el sistema.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="note-legend"><i class="fa-regular fa-note-sticky"></i> Con solicitudes especiales</span>
          <span class="pagination-info"><?php echo count($reservaciones); ?> reservaciones</span>
        </div>
      </div>

      <!-- Tarjetas de Estadísticas de Habitaciones -->
      <div class="stats-grid">
        <div class="stat-card border-green">
          <span class="stat-number"><?php echo $stat_libres; ?></span>
          <span class="stat-label">Libres</span>
        </div>
        <div class="stat-card border-red">
          <span class="stat-number"><?php echo $stat_ocupadas; ?></span>
          <span class="stat-label">Ocupadas</span>
        </div>
        <div class="stat-card border-yellow">
          <span class="stat-number"><?php echo $stat_sucias; ?></span>
          <span class="stat-label">Sucias</span>
        </div>
        <div class="stat-card border-gray">
          <span class="stat-number"><?php echo $stat_mantenimiento; ?></span>
          <span class="stat-label">Mantenimiento</span>
        </div>
      </div>

    </section>

    <!-- Columna Derecha: Formulario Nueva Reservación -->
    <aside class="right-column">
      <div class="reservation-card-box">
        
        <div class="new-res-header">
          <h2>Nueva reservación</h2>
          <span class="channel-badge">Canal: Recepción</span>
        </div>

        <form id="newReservationForm" method="POST" action="">
          <input type="hidden" name="accion" value="nueva_reservacion">
          
          <div class="input-group">
            <label>Nombre del huésped</label>
            <input type="text" name="nombre_huesped" id="guestNameInput" placeholder="Nombre completo" required>
          </div>

          <div class="input-group">
            <label>Teléfono</label>
            <input type="tel" name="telefono" id="guestPhoneInput" placeholder="10 dígitos" maxlength="10" required>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>Entrada</label>
              <input type="date" name="fecha_entrada" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="input-group">
              <label>Salida</label>
              <input type="date" name="fecha_salida" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
            </div>
          </div>

          <div class="input-group">
            <label>Tipo de habitación</label>
            <div class="select-wrapper">
              <select name="tipo_habitacion" required>
                <option value="Sencilla" selected>Sencilla</option>
                <option value="Doble">Doble</option>
              </select>
            </div>
          </div>

          <div class="input-group">
            <label>Notas (opcional)</label>
            <textarea name="notas" placeholder="Solicitudes especiales..."></textarea>
          </div>

          <button type="submit" class="btn-save-reservation">Guardar reservación</button>

          <p class="system-timestamp">Se guardan fecha, hora y tu usuario (<?php echo htmlspecialchars($nombre_usuario); ?>).</p>

        </form>

      </div>
    </aside>

  </main>

  <!-- Script para filtros en tiempo real -->
  <script>
    function filtrarReservaciones() {
      const query = document.getElementById('searchInput').value.toLowerCase();
      const rows = document.querySelectorAll('#reservationsTableBody tr.res-row');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    function filtrarPorEstado(estadoFilter, btnElement) {
      document.querySelectorAll('.filter-pills .pill').forEach(b => b.classList.remove('active'));
      btnElement.classList.add('active');

      const rows = document.querySelectorAll('#reservationsTableBody tr.res-row');

      rows.forEach(row => {
        const estadoRow = row.getAttribute('data-estado');
        if (estadoFilter === 'todas' || estadoRow === estadoFilter) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>