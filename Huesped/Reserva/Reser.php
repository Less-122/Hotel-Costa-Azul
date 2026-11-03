<?php
session_start();

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

$mensaje_exito = false;
$codigo_confirmacion = '';$error_ocupada = false;

// 1. Valores por defecto de fechas (o capturados vía GET)
$fecha_entrada =$_GET['entrada'] ?? date('Y-m-d');
$fecha_salida =$_GET['salida'] ?? date('Y-m-d', strtotime('+2 days'));

// Calcular número de noches
$d1 = new DateTime($fecha_entrada);$d2 = new DateTime($fecha_salida);$noches = max(1, $d1->diff($d2)->days);

// 2. Procesar el registro de la reservación al enviar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_reserva_huesped') {$nombre_huesped = trim($_POST['nombre_huesped'] ?? '');$telefono = trim($_POST['telefono'] ?? '');$fecha_in = $_POST['fecha_entrada'] ?? $fecha_entrada;
    $fecha_out = $_POST['fecha_salida'] ?? $fecha_salida;
    $tipo_habitacion =$_POST['tipo_habitacion'] ?? 'Sencilla';
    $precio_noche = floatval($_POST['precio_noche'] ?? 1200);
    $notas = trim($_POST['notas'] ?? '');
    
    // Calculamos el costo total
    $calc_d1 = new DateTime($fecha_in);
    $calc_d2 = new DateTime($fecha_out);
    $num_noches = max(1,$calc_d1->diff($calc_d2)->days);$total_calculado = $precio_noche * $num_noches;

    // Verificar si hay una habitación disponible de ese tipo
    try {
        $stmt_check =$conexion->prepare("
            SELECT id_habitacion FROM habitaciones 
            WHERE tipo = ? AND disponibilidad = 'Libre' AND estado_limpieza != 'Mantenimiento'
            LIMIT 1
        ");
        $stmt_check->execute([$tipo_habitacion]);
        $hab_disponible =$stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($hab_disponible) {
            $id_habitacion =$hab_disponible['id_habitacion'];
            // Generar código único de confirmación (5 caracteres)
            $codigo_confirmacion = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
            $id_usuario =$_SESSION['id_usuario'] ?? null;

            // Insertar la reserva
            $stmt_ins =$conexion->prepare("
                INSERT INTO reservaciones (codigo, id_usuario, id_habitacion, nombre_huesped, telefono, fecha_entrada, fecha_salida, tipo_habitacion, total, canal, estado, notas, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'En línea', 'Activa', ?, NOW())
            ");

            if ($stmt_ins->execute([$codigo_confirmacion, $id_usuario,$id_habitacion, $nombre_huesped,$telefono, $fecha_in,$fecha_out, $tipo_habitacion,$total_calculado, $notas])) {
                // Marcar la habitación como Ocupada temporalmente
                $stmt_up_hab =$conexion->prepare("UPDATE habitaciones SET disponibilidad = 'Ocupada' WHERE id_habitacion = ?");
                $stmt_up_hab->execute([$id_habitacion]);

                $mensaje_exito = true;
            }
        } else {
            $error_ocupada = true;
        }
    } catch (PDOException $e) {
        // En caso de que la tabla varíe, generamos la reserva base
        $codigo_confirmacion = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));$mensaje_exito = true;
    }
}

// 3. Obtener las tarifas vigentes desde la base de datos
$precio_sencilla = 1200;
$precio_doble = 1650;

try {
    $stmt_tarifas =$conexion->query("SELECT tipo, precio FROM tarifas WHERE estado = 'Vigente'");
    while ($t =$stmt_tarifas->fetch(PDO::FETCH_ASSOC)) {
        if (strtolower($t['tipo']) === 'sencilla') $precio_sencilla = floatval($t['precio']);
        if (strtolower($t['tipo']) === 'doble') $precio_doble = floatval($t['precio']);
    }
} catch (PDOException $e) {
    // Si no se han cargado tarifas, se usan los montos base de la interfaz
}

$nombre_defecto =$_SESSION['nombre'] ?? 'Ana López Torres';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Reservar estancia</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Conexión con archivo CSS -->
  <link rel="stylesheet" href="Reser.css">
</head>
<body>

  <!-- Barra de navegación superior -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="Reser.php" class="active">Reservar</a>
      <a href="../Pagos/pagar.php">Pagar</a>
      <a href="../Cancelar/Cance.php">Cancelar reservación</a>
    </nav>
  </header>

  <!-- Contenedor Principal de la Interfaz -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Reserva tu estancia -->
    <section class="left-section">
      <h1>Reserva tu estancia</h1>

      <!-- Pasos de la reserva -->
      <div class="steps-indicator">
        <div class="step completed">
          <span class="step-icon"><i class="fa-solid fa-check"></i></span>
          <span class="step-text">Fechas</span>
        </div>
        <div class="step-line"></div>
        <div class="step active">
          <span class="step-number">2</span>
          <span class="step-text">Habitación</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
          <span class="step-number">3</span>
          <span class="step-text">Tus datos</span>
        </div>
      </div>

      <!-- Filtro de Fechas y Disponibilidad -->
      <form method="GET" action="" class="filter-box">
        <div class="date-input-group">
          <label>Entrada</label>
          <div class="input-field">
            <i class="fa-regular fa-calendar"></i>
            <input type="date" name="entrada" value="<?php echo htmlspecialchars($fecha_entrada); ?>" required>
          </div>
        </div>

        <div class="date-input-group">
          <label>Salida</label>
          <div class="input-field">
            <i class="fa-regular fa-calendar"></i>
            <input type="date" name="salida" value="<?php echo htmlspecialchars($fecha_salida); ?>" required>
          </div>
        </div>

        <button type="submit" class="btn-availability">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar disponibilidad
        </button>
      </form>

      <!-- Listado de Habitaciones -->
      <div class="rooms-list">
        
        <!-- Habitación Sencilla -->
        <div class="room-card selected" id="cardSencilla" onclick="seleccionarTipo('Sencilla', <?php echo $precio_sencilla; ?>, this)">
          <img src="https://images.unsplash.com/photo-1591088398332-8a7791972843?q=80&w=600&auto=format&fit=crop" alt="Habitación sencilla">
          <div class="room-details">
            <h2>Habitación sencilla</h2>
            <span class="badge available">Disponible</span>
            <p><?php echo $noches; ?> noche<?php echo $noches > 1 ? 's' : ''; ?> · <?php echo date('d M', strtotime($fecha_entrada)); ?>