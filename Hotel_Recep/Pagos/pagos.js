document.addEventListener('DOMContentLoaded', () => {
  const typeBtns = document.querySelectorAll('.type-btn');
  const tipoInput = document.querySelector('#tipoPagoInput');
  const amountInput = document.querySelector('#paymentAmountInput');

  // Si no hay formulario de pago en pantalla (sin búsqueda, reservación liquidada, etc.) no hay nada que hacer
  if (!typeBtns.length || !tipoInput || !amountInput) return;

  const saldo = parseFloat(amountInput.dataset.saldo || '0');

  // Alternar entre Anticipo y Liquidación
  typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      typeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      tipoInput.value = btn.dataset.tipo;

      if (btn.dataset.tipo === 'Liquidación') {
        // La liquidación cubre todo el saldo pendiente
        amountInput.value = saldo.toFixed(2);
      } else {
        amountInput.value = '';
        amountInput.focus();
      }
    });
  });
});
