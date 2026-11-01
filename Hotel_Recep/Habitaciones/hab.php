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
      <a href="../Recepcion/recep.html">Reservaciones</a>
      <a href="hab.html" class="active">Habitaciones</a>
      <a href="#">Pagos</a>
    </nav>
    <div class="user-profile">
      <div class="user-avatar"><i class="fa-regular fa-user"></i></div>
      <div class="user-info">
        <span class="user-name">Marta Pérez</span>
        <span class="user-role">Recepcionista</span>
      </div>
    </div>
  </header>

  <!-- Contenedor Principal -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Tabla y Filtros -->
    <section class="left-column">
      
      <div class="header-title-area">
        <h1>Habitaciones</h1>
        <p>Hoy · 12 nov 2026 · estado de limpieza y disponibilidad en tiempo real</p>
      </div>

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
            <tr class="room-row" data-room="101" data-type="Sencilla" data-clean="Limpia" data-disp="Libre">
              <td><b>101</b></td>
              <td>Sencilla</td>
              <td><span class="badge clean">Limpia</span></td>
              <td><span class="badge free">Libre</span></td>
            </tr>
            <tr class="room-row" data-room="102" data-type="Sencilla" data-clean="Limpia" data-disp="Ocupada">
              <td><b>102</b></td>
              <td>Sencilla</td>
              <td><span class="badge clean">Limpia</span></td>
              <td><span class="badge occupied">Ocupada</span></td>
            </tr>
            <tr class="room-row selected" data-room="103" data-type="Doble" data-clean="Sucia" data-disp="Libre">
              <td><b>103</b></td>
              <td>Doble</td>
              <td><span class="badge dirty">Sucia</span></td>
              <td><span class="badge free">Libre</span></td>
            </tr>
            <tr class="room-row" data-room="104" data-type="Doble" data-clean="Limpia" data-disp="Ocupada">
              <td><b>104</b></td>
              <td>Doble</td>
              <td><span class="badge clean">Limpia</span></td>
              <td><span class="badge occupied">Ocupada</span></td>
            </tr>
            <tr class="room-row" data-room="105" data-type="Sencilla" data-clean="Mantenimiento" data-disp="—">
              <td><b>105</b></td>
              <td>Sencilla</td>
              <td><span class="badge maintenance">Mantenimiento</span></td>
              <td><span class="badge na">—</span></td>
            </tr>
            <tr class="room-row" data-room="106" data-type="Sencilla" data-clean="Limpia" data-disp="Libre">
              <td><b>106</b></td>
              <td>Sencilla</td>
              <td><span class="badge clean">Limpia</span></td>
              <td><span class="badge free">Libre</span></td>
            </tr>
            <tr class="room-row" data-room="107" data-type="Doble" data-clean="Sucia" data-disp="Ocupada">
              <td><b>107</b></td>
              <td>Doble</td>
              <td><span class="badge dirty">Sucia</span></td>
              <td><span class="badge occupied">Ocupada</span></td>
            </tr>
            <tr class="room-row" data-room="108" data-type="Doble" data-clean="Limpia" data-disp="Ocupada">
              <td><b>108</b></td>
              <td>Doble</td>
              <td><span class="badge clean">Limpia</span></td>
              <td><span class="badge occupied">Ocupada</span></td>
            </tr>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="table-hint">Toca una fila para ver o cambiar su estado.</span>
          <span class="pagination-info">8 de 19</span>
        </div>
      </div>

      <!-- Tarjetas de Estadísticas Inferiores -->
      <div class="stats-grid">
        <div class="stat-card border-green">
          <span class="stat-number">7</span>
          <span class="stat-label">Libres</span>
        </div>
        <div class="stat-card border-red">
          <span class="stat-number">9</span>
          <span class="stat-label">Ocupadas</span>
        </div>
        <div class="stat-card border-yellow">
          <span class="stat-number">2</span>
          <span class="stat-label">Sucias</span>
        </div>
        <div class="stat-card border-gray">
          <span class="stat-number">1</span>
          <span class="stat-label">Mantenimiento</span>
        </div>
      </div>

    </section>

    <!-- Columna Derecha: Panel de Modificación de Habitación Seleccionada -->
    <aside class="right-column">
      <div class="room-edit-box">
        
        <div class="edit-header">
          <h2 id="selectedRoomTitle">Habitación 103</h2>
          <span id="selectedRoomType" class="room-type-subtitle">Doble</span>
        </div>

        <form id="roomUpdateForm">
          
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

  <!-- Script -->
  <script src="hab.js"></script>
</body>
</html>