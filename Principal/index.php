<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';

// Tipos que se muestran; el precio sale de la tarifa vigente en la BD
$tipos = [
    'Sencilla' => [
        'titulo' => 'Habitación Sencilla',
        'lema'   => 'Confort y tranquilidad frente al mar',
        'desc'   => 'Cama king size, aire acondicionado, Wi-Fi de alta velocidad, baño privado y televisión por cable.',
        'img'    => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?q=80&w=1400&auto=format&fit=crop',
    ],
    'Doble' => [
        'titulo' => 'Habitación Doble',
        'lema'   => 'Espacio ideal para familias o parejas',
        'desc'   => 'Dos camas matrimoniales, balcón con vista al jardín, cafetera, minibar y amenidades de cortesía.',
        'img'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=1400&auto=format&fit=crop',
    ],
];

$precios = $conexion->query("SELECT tipo_habitacion, precio_noche FROM tarifa WHERE vigente = 1")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
$tipos = array_intersect_key($tipos, $precios);   // solo los que tienen tarifa vigente

$politica = $conexion->query("SELECT descripcion FROM politica_cancelacion WHERE vigente = 1 LIMIT 1")
                     ->fetchColumn();

$usuario_logueado = isset($_SESSION['id_usuario']);
$nombre_usuario   = $_SESSION['nombre'] ?? '';
$rol_usuario      = rolActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Bienvenidos</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="index.css">
</head>
<body>

  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="#habitaciones">Habitaciones</a>
      <a href="#resenas">Reseñas</a>
      <a href="#contacto">Contacto</a>
    </nav>
    <div class="nav-auth">
      <?php if ($usuario_logueado): ?>
        <span class="user-welcome" style="margin-right: 15px; font-weight: 500;">
          Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
        </span>

        <?php if ($rol_usuario !== 'huesped'): ?>
          <a href="<?php echo htmlspecialchars(paginaInicioRol($rol_usuario)); ?>" class="btn-login" style="margin-right: 10px;"><i class="fa-solid fa-hotel"></i> Panel</a>
        <?php endif; ?>

        <a href="../config/logout.php" class="btn-login" style="background-color: #e74c3c; border-color: #e74c3c; color: white;">
          <i class="fa-solid fa-right-from-bracket"></i> Salir
        </a>
      <?php else: ?>
        <a href="../Huesped/Login_regis/login.php" class="btn-login"><i class="fa-regular fa-user"></i>Iniciar sesión</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Carrusel -->
  <section class="carousel-section">
    <div class="carousel-container">
      <?php $primero = true; foreach ($tipos as $tipo => $t): ?>
        <div class="carousel-slide <?php echo $primero ? 'active' : ''; ?>" style="background-image: url('<?php echo $t['img']; ?>');">
          <div class="carousel-caption">
            <h2><?php echo htmlspecialchars($t['titulo']); ?></h2>
            <p><?php echo htmlspecialchars($t['lema']); ?> · Desde $<?php echo number_format($precios[$tipo], 2); ?> / noche</p>
            <a href="../Huesped/Reserva/Reser.php?tipo=<?php echo urlencode($tipo); ?>" class="btn-carousel">Reservar ahora</a>
          </div>
        </div>
      <?php $primero = false; endforeach; ?>

      <button class="carousel-btn prev" onclick="moveSlide(-1)"><i class="fa-solid fa-chevron-left"></i></button>
      <button class="carousel-btn next" onclick="moveSlide(1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
  </section>

  <!-- Tarifas -->
  <section id="habitaciones" class="content-section">
    <div class="section-title">
      <h2>Nuestras Tarifas</h2>
      <p>Elige el espacio perfecto para tu estancia</p>
    </div>

    <div class="rooms-grid-home">
      <?php foreach ($tipos as $tipo => $t): ?>
        <div class="room-home-card">
          <img src="<?php echo str_replace('w=1400', 'w=600', str_replace('q=80&w=1400', 'q=80&w=600', $t['img'])); ?>" alt="<?php echo htmlspecialchars($t['titulo']); ?>">
          <div class="room-home-info">
            <h3><?php echo htmlspecialchars($t['titulo']); ?></h3>
            <p class="room-price">$<?php echo number_format($precios[$tipo], 2); ?> <span>/ noche</span></p>
            <p class="room-desc"><?php echo htmlspecialchars($t['desc']); ?></p>
            <a href="../Huesped/Reserva/Reser.php?tipo=<?php echo urlencode($tipo); ?>" class="btn-home-card">Elegir habitación</a>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (!$tipos): ?>
        <p style="text-align: center; color: #666;">Consulta nuestras tarifas en recepción.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- Reseñas -->
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

  <!-- Footer -->
  <footer id="contacto" class="footer-section">
    <div class="footer-container">
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

  <!-- Modal de políticas -->
  <div id="policyModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <h2>Políticas del Hotel</h2>
      <p>1. Cancelación: <?php echo $politica ? htmlspecialchars($politica) : 'consulta la política vigente en recepción.'; ?></p>
      <p>2. El horario de entrada es a partir de las 15:00 hrs y la salida es a las 12:00 hrs.</p>
      <button class="modal-close-btn" onclick="closePolicyModal()">Entendido</button>
    </div>
  </div>

  <script src="index.js"></script>
</body>
</html>