document.addEventListener('DOMContentLoaded', () => {
  const searchReservationForm = document.querySelector('#searchReservationForm');
  const reservationCodeInput = document.querySelector('#reservationCode');
  
  const typeBtns = document.querySelectorAll('.type-btn');
  const amountInput = document.querySelector('#amountInput');
  const summaryTotalPay = document.querySelector('#summaryTotalPay');
  const btnPayAmountText = document.querySelector('#btnPayAmountText');
  const paymentForm = document.querySelector('#paymentForm');

  let pendingBalanceVal = 1400; // Saldo pendiente inicial de ejemplo

  // Alternar entre tipo de pago (Anticipo / Liquidación)
  typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      typeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const paymentType = btn.getAttribute('data-type');
      
      if (paymentType === 'liquidacion') {
        // Si elige liquidación, se paga el saldo pendiente completo ($1,400)
        amountInput.value = `$${pendingBalanceVal.toLocaleString('en-US')}.00`;
        summaryTotalPay.textContent = `$${pendingBalanceVal.toLocaleString('en-US')}.00`;
        btnPayAmountText.textContent = `$${pendingBalanceVal.toLocaleString('en-US')}.00`;
      } else {
        // Si elige anticipo predeterminado ($500)
        amountInput.value = '$500.00';
        summaryTotalPay.textContent = '$500.00';
        btnPayAmountText.textContent = '$500.00';
      }
    });
  });

  // Sincronizar el input de monto con el resumen de pago en tiempo real
  amountInput.addEventListener('input', () => {
    const val = amountInput.value.trim();
    summaryTotalPay.textContent = val;
    btnPayAmountText.textContent = val;
  });

  // Simulación de búsqueda por código de reserva
  searchReservationForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = reservationCodeInput.value.trim().toUpperCase();
    if (code) {
      alert(`Buscando reservación con código: ${code}`);
    }
  });

  // Envío del formulario de pago
 // Envío del formulario de pago
  paymentForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const cardNumber = document.querySelector('#cardNumber').value.trim();
    const errorAlert = document.querySelector('#paymentErrorAlert');

    // SIMULACIÓN DE VALIDACIÓN: 
    // Si el número de tarjeta contiene "0000" o simula un error, mostramos la alerta visual.
    // De lo contrario, el pago es exitoso.
    const isErrorSimulation = cardNumber.includes('0000'); 

    if (isErrorSimulation) {
      // Muestra la alerta de error exacta que tienes en la imagen
      errorAlert.style.display = 'flex';
    } else {
      // Oculta el error si todo está bien y procede
      errorAlert.style.display = 'none';
      const totalToPay = summaryTotalPay.textContent;
      alert(`¡Pago exitoso por ${totalToPay}! Su transacción ha sido procesada correctamente.`);
    }
  });
}); 