<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hotel Costa Azul - Pagar mi reservación</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Estilos de pago -->
  <link rel="stylesheet" href="pagar.css">
</head>
<body>

  <!-- Navbar Superior -->
  <header class="navbar">
    <div class="nav-brand">Hotel Costa Azul</div>
    <nav class="nav-links">
      <a href="../Reserva/Reser.html">Reservar</a>
      <a href="pagar.html" class="active">Pagar</a>
      <a href="../Cancelar/Cance.html">Cancelar reservación</a>
    </nav>
  </header>

  <!-- Contenedor Principal (Dos columnas) -->
  <main class="main-container">
    
    <!-- Columna Izquierda: Pagar mi reservación -->
    <section class="left-section">
      <h1>Pagar mi reservación</h1>
      <p class="subtitle">Abona un anticipo o liquida tu saldo en línea.</p>

      <!-- Código de reservación y buscador -->
      <form id="searchReservationForm" class="search-form">
        <div class="input-group">
          <label for="reservationCode">Código de reservación</label>
          <div class="input-field">
            <i class="fa-regular fa-ticket"></i>
            <input type="text" id="reservationCode" value="AX7B9" placeholder="Ej. AX7B9" required>
          </div>
        </div>
        <button type="submit" class="btn-search">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </form>

      <!-- Tarjeta de Información de la Reserva -->
      <div class="reservation-summary-card">
        <div class="res-guest-name">Ana López Torres</div>
        <div class="res-info-details">12 → 14 nov 2026 · Sencilla</div>
        
        <div class="res-financials">
          <span class="fin-item">Total <b>$2,400</b></span>
          <span class="fin-dot">·</span>
          <span class="fin-item">Pagado <b>$1,000</b></span>
        </div>

        <div class="balance-row">
          <span class="balance-label">Saldo pendiente</span>
          <span class="balance-amount" id="displayPendingBalance">$1,400</span>
        </div>
      </div>

      <!-- Tipo de pago (Anticipo / Liquidación) -->
      <div class="input-group">
        <label>Tipo de pago</label>
        <div class="payment-type-options">
          <button type="button" class="type-btn active" data-type="anticipo">Anticipo</button>
          <button type="button" class="type-btn" data-type="liquidacion">Liquidación</button>
        </div>
      </div>

      <!-- Monto a pagar -->
      <div class="input-group">
        <label for="amountInput">Monto a pagar</label>
        <div class="input-field">
          <i class="fa-regular fa-credit-card"></i>
          <input type="text" id="amountInput" value="$500.00" required>
        </div>
      </div>
    </section>

    <!-- Columna Derecha: Datos de la tarjeta -->
    <aside class="right-section">
      <div class="card-box">
        <h2>Datos de la tarjeta</h2>

        <form id="paymentForm">
          
          <div class="input-group">
            <label for="cardName">Nombre en la tarjeta</label>
            <div class="input-field">
              <i class="fa-regular fa-user"></i>
              <input type="text" id="cardName" value="Ana López Torres" required>
            </div>
          </div>

          <div class="input-group">
            <label for="cardNumber">Número de tarjeta</label>
            <div class="input-field">
              <i class="fa-regular fa-credit-card"></i>
              <input type="text" id="cardNumber" value="•••• •••• •••• 4242" required>
            </div>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label for="cardExp">Vence</label>
              <div class="input-field">
                <i class="fa-regular fa-calendar"></i>
                <input type="text" id="cardExp" value="08 / 28" required>
              </div>
            </div>

            <div class="input-group">
              <label for="cardCvv">CVV</label>
              <div class="input-field">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="cardCvv" value="•••" maxlength="4" required>
              </div>
            </div>
          </div>

          <!-- Total a pagar destacado -->
          <div class="total-to-pay-box">
            <span>Total a pagar</span>
            <span class="total-pay-amount" id="summaryTotalPay">$500.00</span>
          </div>

          <!-- Botón Pagar -->
          <button type="submit" class="btn-pay-submit">
            <i class="fa-solid fa-lock"></i> Pagar <span id="btnPayAmountText">$500.00</span>
          </button>

          <p class="secure-note">Pago seguro procesado por la pasarela de pagos.</p>

          <a href="../Reserva/Reser.html" class="back-link">← Volver</a>

        </form>
      </div>
    </aside>

  </main>
<!-- Caja de Alerta de Error en el Pago (Aparece al fallar) -->
          <div id="paymentErrorAlert" class="error-alert-box" style="display: none;">
            <i class="fa-solid fa-triangle-exclamation"></i> No se pudo procesar el pago. Revisa los datos.
          </div>

          <!-- Botón Pagar -->
          <button type="submit" class="btn-pay-submit">
            <i class="fa-solid fa-lock"></i> Pagar <span id="btnPayAmountText">$500.00</span>
          </button>
  <!-- Script de pago -->
  <script src="pagar.js"></script>
</body>
</html>