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
      <a href="recep.html" class="active">Reservaciones</a>
      <a href="#">Habitaciones</a>
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
    
    <!-- Columna Izquierda: Tabla y Estadísticas -->
    <section class="left-column">
      
      <div class="header-title-area">
        <h1>Reservaciones</h1>
        <p>Hoy · 12 nov 2026 · incluye reservas en línea y en recepción</p>
      </div>

      <!-- Filtros y Buscador -->
      <div class="filter-toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput" placeholder="Buscar por nombre o código">
        </div>
        <div class="filter-pills">
          <button class="pill active" data-filter="todas">Todas</button>
          <button class="pill" data-filter="activas">Activas</button>
          <button class="pill" data-filter="canceladas">Canceladas</button>
        </div>
      </div>

      <!-- Tabla de Reservaciones -->
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
            <tr>
              <td><b>AX7B9</b></td>
              <td>Ana López</td>
              <td>12 nov</td>
              <td>14 nov</td>
              <td>Sencilla</td>
              <td>En línea</td>
              <td><span class="status-badge active">Activa</span></td>
              <td><i class="fa-regular fa-note-sticky note-icon"></i></td>
            </tr>
            <tr>
              <td><b>K4M2Q</b></td>
              <td>Luis Ortega</td>
              <td>11 nov</td>
              <td>13 nov</td>
              <td>Doble</td>
              <td>Recepción</td>
              <td><span class="status-badge active">Activa</span></td>
              <td></td>
            </tr>
            <tr>
              <td><b>R8T5W</b></td>
              <td>Rosa Medina</td>
              <td>10 nov</td>
              <td>15 nov</td>
              <td>Doble</td>
              <td>En línea</td>
              <td><span class="status-badge active">Activa</span></td>
              <td><i class="fa-regular fa-note-sticky note-icon"></i></td>
            </tr>
            <tr>
              <td><b>B3N6D</b></td>
              <td>Pedro Salas</td>
              <td>09 nov</td>
              <td>10 nov</td>
              <td>Sencilla</td>
              <td>En línea</td>
              <td><span class="status-badge cancelled">Cancelada</span></td>
              <td></td>
            </tr>
            <tr>
              <td><b>Z9P1C</b></td>
              <td>Diana Cruz</td>
              <td>09 nov</td>
              <td>12 nov</td>
              <td>Doble</td>
              <td>Recepción</td>
              <td><span class="status-badge active">Activa</span></td>
              <td></td>
            </tr>
            <tr>
              <td><b>H7L4E</b></td>
              <td>Jorge Vega</td>
              <td>08 nov</td>
              <td>11 nov</td>
              <td>Sencilla</td>
              <td>Recepción</td>
              <td><span class="status-badge finalized">Finalizada</span></td>
              <td><i class="fa-regular fa-note-sticky note-icon"></i></td>
            </tr>
          </tbody>
        </table>

        <div class="table-footer">
          <span class="note-legend"><i class="fa-regular fa-note-sticky"></i> Con solicitudes especiales</span>
          <span class="pagination-info">6 de 24</span>
        </div>
      </div>

      <!-- Tarjetas de Estadísticas de Habitaciones -->
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

    <!-- Columna Derecha: Formulario Nueva Reservación -->
    <aside class="right-column">
      <div class="reservation-card-box">
        
        <div class="new-res-header">
          <h2>Nueva reservación</h2>
          <span class="channel-badge">Canal: Recepción</span>
        </div>

        <form id="newReservationForm">
          
          <div class="input-group">
            <label>Nombre del huésped</label>
            <input type="text" id="guestNameInput" placeholder="Nombre completo" required>
          </div>

          <div class="input-group">
            <label>Teléfono</label>
            <input type="tel" id="guestPhoneInput" placeholder="10 dígitos" maxlength="10" required>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label>Entrada</label>
              <input type="text" placeholder="dd/mm" required>
            </div>
            <div class="input-group">
              <label>Salida</label>
              <input type="text" placeholder="dd/mm" required>
            </div>
          </div>

          <div class="input-group">
            <label>Tipo de habitación</label>
            <div class="select-wrapper">
              <select required>
                <option value="Sencilla" selected>Sencilla</option>
                <option value="Doble">Doble</option>
              </select>
            </div>
          </div>

          <div class="input-group">
            <label>Notas (opcional)</label>
            <textarea placeholder="Solicitudes especiales..."></textarea>
          </div>

          <button type="submit" class="btn-save-reservation">Guardar reservación</button>

          <p class="system-timestamp">Se guardan fecha, hora y tu usuario.</p>

        </form>

      </div>
    </aside>

  </main>

  <!-- Script -->
  <script src="recep.js"></script>
</body>
</html>