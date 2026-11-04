<?php
session_start();

// Conexión a la base de datos (ajusta la ruta según tu estructura)
require_once '../config/conexion.php';

// Variables por defecto para tarifas
$precio_sencilla = 1200;
$precio_doble = 1650;
$precio_suite = 2800;

try {
    // Consultar las tarifas vigentes desde la base de datos
    $stmt_tarifas = $conexion->query("SELECT tipo, precio FROM tarifas WHERE estado = 'Vigente'");
    while ($t = $stmt_tarifas->fetch(PDO::FETCH_ASSOC)) {
        if (strtolower($t['tipo']) === 'sencilla') $precio_sencilla = floatval($t['precio']);
        if (strtolower($t['tipo']) === 'doble') $precio_doble = floatval($t['precio']);
        if (strtolower($t['tipo']) === 'suite') $precio_suite = floatval($t['precio']);
    }
} catch (PDOException $e) {
    // Si falla la consulta, conserva los valores base
}

// Consultar la política vigente para el modal
$politica_vigente_texto = "Las cancelaciones sin penalización deben realizarse con al menos 48 horas de anticipación a la fecha de entrada.";
try {
    $stmt_poli = $conexion->query("SELECT descripcion FROM politicas WHERE estado = 'Vigente' LIMIT 1");
    $pol_data = $stmt_poli->fetch(PDO::FETCH_ASSOC);
    if ($pol_data && !empty($pol_data['descripcion'])) {
        $politica_vigente_texto = $pol_data['descripcion'];
    }
} catch (PDOException $e) {
    // Conservar texto predeterminado si hay error
}

$usuario_logueado = isset($_SESSION['id_usuario']);
$nombre_usuario = $_SESSION['nombre'] ?? '';
$rol_usuario = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Bienvenidos</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos del Index -->
  <link rel="stylesheet" href="index.css">
</head>
<body>

  <!-- Barra de Navegación Superior -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="#habitaciones">Habitaciones</a>
      <a href="#promociones">Promociones</a>
      <a href="#resenas">Reseñas</a>
      <a href="#contacto">Contacto</a>
    </nav>
    <div class="nav-auth">
      <?php if ($usuario_logueado): ?>
        <span class="user-welcome" style="margin-right: 15px; font-weight: 500;">
          Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
        </span>
        
        <?php if ($rol_usuario === 'admin' || $rol_usuario === 'recepcion'): ?>
          <a href="vistas/recepcion/dashboard.php" class="btn-login" style="margin-right: 10px;"><i class="fa-solid fa-hotel"></i> Panel</a>
        <?php elseif ($rol_usuario === 'camarista'): ?>
          <a href="vistas/camaristas/panel.php" class="btn-login" style="margin-right: 10px;"><i class="fa-solid fa-broom"></i> Panel</a>
        <?php endif; ?>

        <a href="controladores/logout.php" class="btn-login" style="background-color: #e74c3c; border-color: #e74c3c; color: white;">
          <i class="fa-solid fa-right-from-bracket"></i> Salir
        </a>
      <?php else: ?>
        <a href="..\Huesped\Login_regis\index.php" class="btn-login"><i class="fa-regular fa-user"></i>Iniciar sesión</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Sección Carrusel de Habitaciones -->
  <section class="carousel-section">
    <div class="carousel-container">
      <div class="carousel-slide active" style="background-image: url('https://images.unsplash.com/photo-1590490360182-c33d57733427?q=80&w=1400&auto=format&fit=crop');">
        <div class="carousel-caption">
          <h2>Habitación Sencilla</h2>
          <p>Confort y tranquilidad frente al mar · Desde $<?php echo number_format($precio_sencilla, 2); ?> / noche</p>
          <a href="Reserva/Reser.php" class="btn-carousel">Reservar ahora</a>
        </div>
      </div>

      <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=1400&auto=format&fit=crop');">
        <div class="carousel-caption">
          <h2>Habitación Doble</h2>
          <p>Espacio ideal para familias o parejas · Desde $<?php echo number_format($precio_doble, 2); ?> / noche</p>
          <a href="Reserva/Reser.php" class="btn-carousel">Reservar ahora</a>
        </div>
      </div>

      <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1566665797739-1674de7a421a?q=80&w=1400&auto=format&fit=crop');">
        <div class="carousel-caption">
          <h2>Suite Costa Azul</h2>
          <p>Vista panorámica y exclusividad total · Desde $<?php echo number_format($precio_suite, 2); ?> / noche</p>
          <a href="Reserva/Reser.php" class="btn-carousel">Reservar ahora</a>
        </div>
      </div>

      <!-- Controles del Carrusel -->
      <button class="carousel-btn prev" onclick="moveSlide(-1)"><i class="fa-solid fa-chevron-left"></i></button>
      <button class="carousel-btn next" onclick="moveSlide(1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
  </section>

  <!-- Sección de Precios y Habitaciones -->
  <section id="habitaciones" class="content-section">
    <div class="section-title">
      <h2>Nuestras Tarifas</h2>
      <p>Elige el espacio perfecto para tu estancia</p>
    </div>

    <div class="rooms-grid-home">
      <div class="room-home-card">
        <img src="https://images.unsplash.com/photo-1590490360182-c33d57733427?q=80&w=600&auto=format&fit=crop" alt="Habitación Sencilla">
        <div class="room-home-info">
          <h3>Habitación Sencilla</h3>
          <p class="room-price">$<?php echo number_format($precio_sencilla, 2); ?> <span>/ noche</span></p>
          <p class="room-desc">Cama king size, aire acondicionado, Wi-Fi de alta velocidad, baño privado y televisión por cable.</p>
          <a href="Reserva/Reser.php?tipo=Sencilla" class="btn-home-card">Elegir habitación</a>
        </div>
      </div>

      <div class="room-home-card">
        <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=600&auto=format&fit=crop" alt="Habitación Doble">
        <div class="room-home-info">
          <h3>Habitación Doble</h3>
          <p class="room-price">$<?php echo number_format($precio_doble, 2); ?> <span>/ noche</span></p>
          <p class="room-desc">Dos camas matrimoniales, balcón con vista al jardín, cafetera, minibar y amenidades de cortesía.</p>
          <a href="Reserva/Reser.php?tipo=Doble" class="btn-home-card">Elegir habitación</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Sección de Promociones -->
  <section id="promociones" class="promo-section">
    <div class="promo-box">
      <div class="promo-icon"><i class="fa-solid fa-tag"></i></div>
      <div class="promo-text">
        <h2>Promoción de Temporada: ¡Escapada de Fin de Semana!</h2>
        <p>Reserva de viernes a domingo en fechas seleccionadas y obtén un <strong>15% de descuento</strong> en tu estancia aplicando el código de anticipo.</p>
      </div>
      <a href="Reserva/Reser.php" class="btn-promo">Aprovechar oferta</a>
    </div>
  </section>

  <!-- Sección de Reseñas -->
  <section id="resenas" class="content-section bg-light">
    <div class="section-title">
      <h2>Lo que dicen nuestros huéspedes</h2>
      <p>Experiencias reales en Hotel Costa Azul</p>
    </div>

    <div class="reviews-grid">
      <div class="review-card">
        <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <p>"Un lugar excepcional para descansar. Las instalaciones están impecables y la atención en recepción es sumamente amable. Definitivamente volveré."</p>
        <span class="reviewer">— Ana López Torres</span>
      </div>
      <div class="review-card">
        <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <p>"Excelente ubicación y habitaciones muy cómodas. El proceso de reservación en línea fue rapidísimo. 100% recomendado."</p>
        <span class="reviewer">— Luis Ortega</span>
      </div>
    </div>
  </section>

  <!-- Pie de Página (Footer) -->
  <footer id="contacto" class="footer-section">
    <div class="footer-container">
      
      <!-- Columna 1: Info y Contacto -->
      <div class="footer-col">
        <h3>Hotel Costa Azul</h3>
        <p><i class="fa-solid fa-phone"></i> Teléfono: +52 (777) 123-4567</p>
        <p><i class="fa-solid fa-envelope"></i> Dudas y sugerencias: contacto@hotelcostaazul.com</p>
        <div class="social-icons">
          <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#"><i class="fa-brands fa-instagram"></i></a>
          <a href="#"><i class="fa-brands fa-twitter"></i></a>
        </div>
      </div>

      <!-- Columna 2: Mapa de Ubicación -->
      <div class="footer-col">
        <h3>Ubicación</h3>
        <div class="map-box">
          <i class="fa-solid fa-map-location-dot"></i>
          <span>Blvd. Costero #104, Zona Dorada, Morelos, México.</span>
        </div>
        <button class="btn-policies" onclick="openPolicyModal()">Políticas del Hotel</button>
      </div>

    </div>
    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> Hotel Costa Azul. Todos los derechos reservados.</p>
    </div>
  </footer>

  <!-- Modal de Políticas del Hotel Dinámico -->
  <div id="policyModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <h2>Políticas del Hotel</h2>
      <p>1. Las reservaciones requieren un anticipo mínimo del 30% para garantizar la estancia.</p>
      <p>2. <?php echo htmlspecialchars($politica_vigente_texto); ?></p>
      <p>3. El horario de entrada (Check-in) es a partir de las 15:00 hrs y la salida (Check-out) es a las 12:00 hrs.</p>
      <button class="modal-close-btn" onclick="closePolicyModal()">Entendido</button>
    </div>
  </div>

  <!-- Script de Interacciones y Modal -->
  <script src="index.js"></script>
  <script>
    function openPolicyModal() {
      document.getElementById('policyModal').style.display = 'flex';
    }
    function closePolicyModal() {
      document.getElementById('policyModal').style.display = 'none';
    }
  </script>
</body>
</html>