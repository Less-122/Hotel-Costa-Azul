<?php
require_once '../../config/sesion.php';
verificarAcceso(['recepcionista', 'admin']);

require_once '../../config/conexion.php';

function fecha(string $f, bool $con_anio = false): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($f);
    return date('d', $t) . ' ' . $meses[date('n', $t) - 1] . ($con_anio ? ' ' . date('Y', $t) : '');
}

$id_usuario    = (int) $_SESSION['id_usuario'];
$mensaje_exito = '';
$mensaje_error = '';

// Avisos tras guardar o cancelar (se redirige después del POST para que F5 no lo repita)
if (($_GET['ok'] ?? '') === '1' && preg_match('/^[A-Z0-9]{5}$/', $_GET['codigo'] ?? '')) {
    $mensaje_exito = "¡Reservación creada con éxito! Código asignado: " . $_GET['codigo'];
} elseif (($_GET['cancelada'] ?? '') === '1') {
    $mensaje_exito = "Reservación cancelada correctamente.";
}

$tipos_habitacion = $conexion->query("SELECT DISTINCT tipo_habitacion FROM habitacion ORDER BY tipo_habitacion")
                             ->fetchAll(PDO::FETCH_COLUMN);

$hoy  = date('Y-m-d');
$form = [
    'nombre_huesped'  => '',
    'telefono'        => '',
    'correo'          => '',
    'fecha_entrada'   => $hoy,
    'fecha_salida'    => date('Y-m-d', strtotime('+1 day')),
    'tipo_habitacion' => $tipos_habitacion[0] ?? 'Sencilla',
    'notas'           => '',
];

$accion = ($_SERVER['REQUEST_METHOD'] === 'POST') ? ($_POST['accion'] ?? '') : '';

// 1. Cancelar una reservación (RF-09). Queda registrado quién y cuándo (RNF-03).
if ($accion === 'cancelar_reservacion') {
    try {
        $st = $conexion->prepare(
            "UPDATE reservacion
             SET estado = 'Cancelada', modificado_por = ?, fecha_modificacion = NOW()
             WHERE id_reservacion = ? AND estado = 'Activa'"
        );
        $st->execute([$id_usuario, (int) ($_POST['id_reservacion'] ?? 0)]);

        if ($st->rowCount() === 1) {
            header("Location: recep.php?cancelada=1");
            exit;
        }
        $mensaje_error = "No se pudo cancelar: la reservación no existe o ya no está activa.";
    } catch (PDOException $e) {
        error_log('Recepción - cancelar: ' . $e->getMessage());
        $mensaje_error = "No se pudo cancelar la reservación.";
    }
}

// 2. Crear una reservación desde Recepción
if ($accion === 'nueva_reservacion') {
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

            // Primer paso de la transacción: bloquear las habitaciones del tipo. Si dos reservaciones
            // llegan a la vez, la segunda espera y ya ve la primera (sin traslapes).
            $conexion->prepare("SELECT numero_habitacion FROM habitacion WHERE tipo_habitacion = ? FOR UPDATE")
                     ->execute([$form['tipo_habitacion']]);

            // Huésped: se usa la cuenta existente (por correo) o se crea una nueva
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
                // Cuenta de mostrador: sin correo se genera uno interno y la contraseña es aleatoria
                $correo_cuenta = $form['correo'] !== '' ? $form['correo'] : 'sin-correo-' . bin2hex(random_bytes(6)) . '@costaazul.invalid';
                $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $conexion->prepare("INSERT INTO usuario (nombre, contacto, correo, password, rol) VALUES (?, ?, ?, ?, 'Huesped')")
                         ->execute([$form['nombre_huesped'], $form['telefono'], $correo_cuenta, $hash]);
                $id_huesped = (int) $conexion->lastInsertId();
            }

            // Habitación libre del tipo pedido, sin traslape de fechas (RF-12)
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
            ");
            $st->execute([$form['tipo_habitacion'], $form['fecha_entrada'], $hoy, $form['fecha_salida'], $form['fecha_entrada']]);
            $numero_habitacion = $st->fetchColumn();
            if ($numero_habitacion === false) {
                throw new RuntimeException("No hay habitaciones " . $form['tipo_habitacion'] . " disponibles en esas fechas.");
            }

            // Tarifa y política vigentes
            $st = $conexion->prepare("SELECT id_tarifa FROM tarifa WHERE tipo_habitacion = ? AND vigente = 1 LIMIT 1");
            $st->execute([$form['tipo_habitacion']]);
            $id_tarifa   = $st->fetchColumn();
            $id_politica = $conexion->query("SELECT id_politica FROM politica_cancelacion WHERE vigente = 1 LIMIT 1")->fetchColumn();
            if ($id_tarifa === false || $id_politica === false) {
                throw new RuntimeException("No hay tarifa o política de cancelación vigente. Avisa al administrador.");
            }

            // Código de confirmación único de 5 caracteres
            $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $existe   = $conexion->prepare("SELECT 1 FROM reservacion WHERE numero_confirmacion = ?");
            do {
                $codigo = '';
                for ($i = 0; $i < 5; $i++) {
                    $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $existe->execute([$codigo]);
            } while ($existe->fetchColumn());

            // Guardar: la BD pone fecha y hora; se registra el usuario de recepción
            $conexion->prepare("
                INSERT INTO reservacion
                    (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal,
                     id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica)
                VALUES (?, ?, ?, 'Activa', ?, 'Recepción', ?, ?, ?, ?, ?)
            ")->execute([
                $codigo, $form['fecha_entrada'], $form['fecha_salida'],
                $form['notas'] !== '' ? $form['notas'] : null,
                $id_huesped, $id_usuario, $numero_habitacion, $id_tarifa, $id_politica,
            ]);

            $conexion->commit();
            header("Location: recep.php?ok=1&codigo=" . $codigo);
            exit;
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

// 3. Lista de reservaciones (las 50 más recientes, en línea y de recepción)
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

// 4. Contadores de habitaciones
$stats = $conexion->query("
    SELECT SUM(disponible = 1 AND estado_limpieza <> 'Mantenimiento') AS libres,
           SUM(disponible = 0 AND estado_limpieza <> 'Mantenimiento') AS ocupadas,
           SUM(estado_limpieza = 'Sucia')         AS sucias,
           SUM(estado_limpieza = 'Mantenimiento') AS mantenimiento
    FROM habitacion
")->fetch(PDO::FETCH_ASSOC);

$nombre_usuario = $_SESSION['nombre'] ?? 'Recepción';
$rol_usuario    = ucfirst($_SESSION['rol'] ?? 'Recepcionista');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Panel de Recepción</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="recep.css">
</head>
<body>

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

  <main class="main-container">

    <section class="left-column">

      <div class="header-title-area">
        <h1>Reservaciones</h1>
        <p>Hoy · <?php echo fecha($hoy, true); ?> · incluye reservas en línea y en recepción</p>
      </div>

      <?php if ($mensaje_exito): ?>
          <div class="alert alert-success" style="color: green; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_exito); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
          <div class="alert alert-danger" style="color: red; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

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
              <th>ACCIONES</th>
            </tr>
          </thead>
          <tbody id="reservationsTableBody">
            <?php foreach ($reservaciones as $res): ?>
              <?php
                $estado_raw   = strtolower($res['estado']);
                $estado_class = ($estado_raw === 'cancelada') ? 'cancelled' : (($estado_raw === 'finalizada') ? 'finalized' : 'active');
              ?>
              <tr class="res-row" data-estado="<?php echo htmlspecialchars($estado_raw); ?>">
                <td><b><?php echo htmlspecialchars($res['codigo']); ?></b></td>
                <td class="guest-cell"><?php echo htmlspecialchars($res['nombre_huesped']); ?></td>
                <td><?php echo fecha($res['fecha_entrada']); ?></td>
                <td><?php echo fecha($res['fecha_salida']); ?></td>
                <td><?php echo htmlspecialchars($res['tipo_habitacion'] . ' · ' . $res['numero_habitacion']); ?></td>
                <td><?php echo htmlspecialchars($res['canal']); ?></td>
                <td><span class="status-badge <?php echo $estado_class; ?>"><?php echo htmlspecialchars($res['estado']); ?></span></td>
                <td>
                  <?php if (!empty($res['notas'])): ?>
                    <i class="fa-regular fa-note-sticky note-icon" title="<?php echo htmlspecialchars($res['notas']); ?>"></i>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($estado_raw === 'activa'): ?>
                    <form method="POST" onsubmit="return confirm('¿Cancelar esta reservación?');">
                      <input type="hidden" name="accion" value="cancelar_reservacion">
                      <input type="hidden" name="id_reservacion" value="<?php echo (int) $res['id_reservacion']; ?>">
                      <button type="submit" class="btn-cancel-row">Cancelar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (!$reservaciones): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 20px;">No hay reservaciones registradas en el sistema.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="note-legend"><i class="fa-regular fa-note-sticky"></i> Con solicitudes especiales</span>
          <span class="pagination-info"><?php echo count($reservaciones); ?> reservaciones</span>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card border-green">
          <span class="stat-number"><?php echo (int) $stats['libres']; ?></span>
          <span class="stat-label">Libres</span>
        </div>
        <div class="stat-card border-red">
          <span class="stat-number"><?php echo (int) $stats['ocupadas']; ?></span>
          <span class="stat-label">Ocupadas</span>
        </div>
        <div class="stat-card border-yellow">
          <span class="stat-number"><?php echo (int) $stats['sucias']; ?></span>
          <span class="stat-label">Sucias</span>
        </div>
        <div class="stat-card border-gray">
          <span class="stat-number"><?php echo (int) $stats['mantenimiento']; ?></span>
          <span class="stat-label">Mantenimiento</span>
        </div>
      </div>

    </section>

    <aside class="right-column">
      <div class="reservation-card-box">

        <div class="new-res-header">
          <h2>Nueva reservación</h2>
          <span class="channel-badge">Canal: Recepción</span>
        </div>

        <form id="newReservationForm" method="POST">
          <input type="hidden" name="accion" value="nueva_reservacion">

          <div class="input-group">
            <label>Nombre del huésped</label>
            <input type="text" name="nombre_huesped" maxlength="100" value="<?php echo htmlspecialchars($form['nombre_huesped']); ?>" placeholder="Nombre completo" required>
          </div>

          <div class="input-group">
            <label>Teléfono</label>
            <input type="tel" name="telefono" placeholder="10 dígitos" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($form['telefono']); ?>" required>
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

  <script>
    function filtrarReservaciones() {
      const query = document.getElementById('searchInput').value.toLowerCase();
      document.querySelectorAll('#reservationsTableBody tr.res-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
      });
    }

    function filtrarPorEstado(estado, btn) {
      document.querySelectorAll('.filter-pills .pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('#reservationsTableBody tr.res-row').forEach(row => {
        row.style.display = (estado === 'todas' || row.dataset.estado === estado) ? '' : 'none';
      });
    }
  </script>
</body>
</html>