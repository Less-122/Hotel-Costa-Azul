document.addEventListener('DOMContentLoaded', () => {
  const typeBtns = document.querySelectorAll('.type-btn');
  const paymentAmountInput = document.querySelector('#paymentAmountInput');
  const registerPaymentForm = document.querySelector('#registerPaymentForm');
  const pendingBalanceDisplay = document.querySelector('#pendingBalanceDisplay');
  const newPendingBalanceText = document.querySelector('#newPendingBalanceText');
  const successPaymentBanner = document.querySelector('#successPaymentBanner');
  
  let currentBalance = 1400; // Saldo pendiente inicial de ejemplo

  // Alternar entre Anticipo y Liquidación
  typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      typeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const paymentType = btn.getAttribute('data-type');
      if (paymentType === 'liquidacion') {
        paymentAmountInput.value = `$${currentBalance.toLocaleString('en-US')}.00`;
      } else {
        paymentAmountInput.value = '$500.00';
      }
    });
  });

  // Registro de pago en recepción
  registerPaymentForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Simulamos la reducción del saldo pendiente
    currentBalance = Math.max(0, currentBalance - 500);
    
    const updatedStr = `$${currentBalance.toLocaleString('en-US')}`;
    pendingBalanceDisplay.textContent = updatedStr;
    newPendingBalanceText.textContent = updatedStr;

    // Mostrar el banner verde de éxito
    successPaymentBanner.style.display = 'flex';
  });
});