<?php
require_once '../../config/sesion.php';
verificarAcceso(['recepcionista', 'admin']);   // solo recepción (o admin)

require_once '../../config/conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

// Aviso tras guardar (se redirige después del POST para que F5 no duplique la reservación)
if (($_GET['ok'] ?? '') === '1' && preg_match('/^[A-Z0-9]{5}$/', $_GET['codigo'] ?? '')) {
    $mensaje_exito = "¡Reservación creada con éxito! Código asignado: <strong>" . $_GET['codigo'] . "</strong>";
}

// Tipos de habitación que realmente existen en el hotel
$tipos_habitacion = $conexion->query("SELECT DISTINCT tipo_habitacion FROM habitacion ORDER BY tipo_habitacion")
                             ->fetchAll(PDO::FETCH_COLUMN);

$hoy  = date('Y-m-d');
$form = [
    'nombre_huesped' => '',
    'telefono'       => '',
    'correo'         => '',
    'fecha_entrada'  => $hoy,
    'fecha_salida'   => date('Y-m-d', strtotime('+1 day')),
    'tipo_habitacion'=> $tipos_habitacion[0] ?? 'Sencilla',
    'notas'          => '',
];

// 1. Crear una reservación desde Recepción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'nueva_reservacion') {
    foreach ($form as $campo => $_) {
        $form[$campo] = trim($_POST[$campo] ?? $form[$campo]);
    }

    $f_ent = DateTime::createFromFormat('Y-m-d', $form['fecha_entrada']);
    $f_sal = DateTime::createFromFormat('Y-m-d', $form['fecha_salida']);
    $fechas_ok = $f_ent && $f_sal
        && $f_ent->format('Y-m-d') === $form['fecha_entrada']
        && $f_sal->format('Y-m-d') === $form['fecha_salida'];

    if ($form['nombre_huesped'] === '' || $form['telefono'] === '' || $form['fecha_entrada'] === '' || $form['fecha_salida'] === '') {
        $mensaje_error = "Por favor completa todos los campos obligatorios.";
    } elseif (mb_strlen($form['nombre_huesped']) > 100) {
        $mensaje_error = "El nombre es demasiado largo (máximo 100 caracteres).";
    } elseif (!preg_match('/^\d{10}$/', $form['telefono'])) {
        $mensaje_error = "El teléfono debe tener 10 dígitos.";
    } elseif ($form['correo'] !== '' && (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL) || strlen($form['correo']) > 80)) {
        $mensaje_error = "El correo electrónico no es válido.";
    } elseif (!$fechas_ok) {
        $mensaje_error = "Las fechas no son válidas.";
    } elseif ($form['fecha_entrada'] < $hoy) {
        $mensaje_error = "La fecha de entrada no puede ser anterior a hoy.";
    } elseif ($form['fecha_salida'] <= $form['fecha_entrada']) {
        $mensaje_error = "La salida debe ser posterior a la entrada.";
    } elseif (!in_array($form['tipo_habitacion'], $tipos_habitacion, true)) {
        $mensaje_error = "Tipo de habitación no válido.";
    } else {
        try {
            $conexion->beginTransaction();

            // --- Huésped: se usa la cuenta existente (por correo) o se crea una nueva ---
            $id_huesped = null;
            if ($form['correo'] !== '') {
                $st = $conexion->prepare("SELECT id_usuario, rol FROM usuario WHERE correo = ?");
                $st->execute([$form['correo']]);
                $existente = $st->fetch(PDO::FETCH_ASSOC);
                if ($existente) {
                    if (strtolower($existente['rol']) !== 'huesped') {
                        throw new RuntimeException("Ese correo pertenece a personal del hotel, no a un huésped.");
                    }
                    $id_huesped = (int) $existente['id_usuario'];
                }
            }
            if ($id_huesped === null) {
                // Cuenta de huésped de mostrador: sin correo se genera uno interno y la contraseña
                // es aleatoria (se guarda ya hasheada); el huésped puede registrarse después con su correo.
                $correo_cuenta = $form['correo'] !== '' ? $form['correo'] : 'sin-correo-' . bin2hex(random_bytes(6)) . '@costaazul.invalid';
                $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $st = $conexion->prepare("INSERT INTO usuario (nombre, contacto, correo, password, rol) VALUES (?, ?, ?, ?, 'Huesped')");
                $st->execute([$form['nombre_huesped'], $form['telefono'], $correo_cuenta, $hash]);
                $id_huesped = (int) $conexion->lastInsertId();
            }

            // --- Habitación libre del tipo pedido, sin traslape de fechas (RF-12) ---
            $st = $conexion->prepare("
                SELECT h.numero_habitacion
                FROM habitacion h
                WHERE h.tipo_habitacion = ?
                  AND h.estado_limpieza <> 'Mantenimiento'
                  AND (? > ? OR h.disponible = 1)
                  AND NOT EXISTS (
                        SELECT 1 FROM reservacion r
                        WHERE r.numero_habitacion = h.numero_habitacion
                          AND r.estado = 'Activa'
                          AND r.fecha_entrada < ?
                          AND r.fecha_salida  > ?
                  )
                ORDER BY h.numero_habitacion
                LIMIT 1
                FOR UPDATE
            ");
            $st->execute([$form['tipo_habitacion'], $form['fecha_entrada'], $hoy, $form['fecha_salida'], $form['fecha_entrada']]);
            $numero_habitacion = $st->fetchColumn();
            if ($numero_habitacion === false) {
                throw new RuntimeException("No hay habitaciones " . $form['tipo_habitacion'] . " disponibles en esas fechas.");
            }

            // --- Tarifa y política vigentes ---
            $st = $conexion->prepare("SELECT id_tarifa FROM tarifa WHERE tipo_habitacion = ? AND vigente = 1 LIMIT 1");
            $st->execute([$form['tipo_habitacion']]);
            $id_tarifa = $st->fetchColumn();
            $id_politica = $conexion->query("SELECT id_politica FROM politica_cancelacion WHERE vigente = 1 LIMIT 1")->fetchColumn();
            if ($id_tarifa === false || $id_politica === false) {
                throw new RuntimeException("No hay tarifa o política de cancelación vigente. Avisa al administrador.");
            }

            // --- Código de confirmación único de 5 caracteres ---
            $existe = $conexion->prepare("SELECT COUNT(*) FROM reservacion WHERE numero_confirmacion = ?");
            do {
                $codigo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
                $existe->execute([$codigo]);
            } while ((int) $existe->fetchColumn() > 0);

            // --- Guardar: fecha/hora la pone la BD; se registra el usuario de recepción ---
            $st = $conexion->prepare("
                INSERT INTO reservacion
                    (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal,
                     id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica)
                VALUES (?, ?, ?, 'Activa', ?, 'Recepción', ?, ?, ?, ?, ?)
            ");
            $st->execute([
                $codigo, $form['fecha_entrada'], $form['fecha_salida'],
                $form['notas'] !== '' ? $form['notas'] : null,
                $id_huesped, $_SESSION['id_usuario'], $numero_habitacion, $id_tarifa, $id_politica,
            ]);

            $conexion->commit();
            header("Location: recep.php?ok=1&codigo=" . $codigo);
            exit();
        } catch (RuntimeException $e) {
            if ($conexion->inTransaction()) { $conexion->rollBack(); }
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) { $conexion->rollBack(); }
            error_log('Recepción - nueva reservación: ' . $e->getMessage());
            $mensaje_error = "No se pudo guardar la reservación. Inténtalo de nuevo.";
        }
    }
}

// 2. Lista de reservaciones (las 50 más recientes, en línea y de recepción)
try {
    $reservaciones = $conexion->query("
        SELECT r.id_reservacion,
               r.numero_confirmacion AS codigo,
               u.nombre              AS nombre_huesped,
               r.fecha_entrada, r.fecha_salida,
               h.tipo_habitacion, r.numero_habitacion,
               r.canal, r.estado, r.notas
        FROM reservacion r
        JOIN usuario u    ON u.id_usuario = r.id_huesped
        JOIN habitacion h ON h.numero_habitacion = r.numero_habitacion
        ORDER BY r.fecha_registro DESC, r.id_reservacion DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Recepción - listar reservaciones: ' . $e->getMessage());
    $reservaciones = [];
}

// 3. Contadores de habitaciones
$stat_libres = $stat_ocupadas = $stat_sucias = $stat_mantenimiento = 0;
try {
    $st = $conexion->query("
        SELECT SUM(disponible = 1 AND estado_limpieza <> 'Mantenimiento') AS libres,
               SUM(disponible = 0 AND estado_limpieza <> 'Mantenimiento') AS ocupadas,
               SUM(estado_limpieza = 'Sucia')         AS sucias,
               SUM(estado_limpieza = 'Mantenimiento') AS mantenimiento
        FROM habitacion
    ")->fetch(PDO::FETCH_ASSOC);
    $stat_libres        = (int) $st['libres'];
    $stat_ocupadas      = (int) $st['ocupadas'];
    $stat_sucias        = (int) $st['sucias'];
    $stat_mantenimiento = (int) $st['mantenimiento'];
} catch (PDOException $e) {
    error_log('Recepción - contadores: ' . $e->getMessage());
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Recepción';
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
      <a href="../../config/logout.php" title="Cerrar sesión" style="margin-left: 14px; color: inherit;"><i class="fa-solid fa-right-from-bracket"></i></a>
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
              <th>HABITACIÓN</th>
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
                <tr class="res-row" data-estado="<?php echo htmlspecialchars($estado_raw); ?>">
                  <td><b><?php echo htmlspecialchars($res['codigo']); ?></b></td>
                  <td class="guest-cell"><?php echo htmlspecialchars($res['nombre_huesped']); ?></td>
                  <td><?php echo date('d M', strtotime($res['fecha_entrada'])); ?></td>
                  <td><?php echo date('d M', strtotime($res['fecha_salida'])); ?></td>
                  <td><?php echo htmlspecialchars($res['tipo_habitacion'] . ' · ' . $res['numero_habitacion']); ?></td>
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
            <input type="text" name="nombre_huesped" id="guestNameInput" maxlength="100" value="<?php echo htmlspecialchars($form['nombre_huesped']); ?>" placeholder="Nombre completo" required>
          </div>

          <div class="input-group">
            <label>Teléfono</label>
            <input type="tel" name="telefono" id="guestPhoneInput" placeholder="10 dígitos" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($form['telefono']); ?>" required>
          </div>

          <div class="input-group">
            <label>Correo (opcional)</label>
            <input type="email" name="correo" maxlength="80" value="<?php echo htmlspecialchars($form['correo']); ?>" placeholder="Si ya tiene cuenta, se usa esa">
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>Entrada</label>
              <input type="date" name="fecha_entrada" min="<?php echo $hoy; ?>" value="<?php echo htmlspecialchars($form['fecha_entrada']); ?>" required>
            </div>
            <div class="input-group">
              <label>Salida</label>
              <input type="date" name="fecha_salida" min="<?php echo $hoy; ?>" value="<?php echo htmlspecialchars($form['fecha_salida']); ?>" required>
            </div>
          </div>

          <div class="input-group">
            <label>Tipo de habitación</label>
            <div class="select-wrapper">
              <select name="tipo_habitacion" required>
                <?php foreach ($tipos_habitacion as $tipo): ?>
                  <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $tipo === $form['tipo_habitacion'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="input-group">
            <label>Notas (opcional)</label>
            <textarea name="notas" placeholder="Solicitudes especiales..."><?php echo htmlspecialchars($form['notas']); ?></textarea>
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