document.addEventListener('DOMContentLoaded', () => {
  const typeBtns    = document.querySelectorAll('.type-btn');
  const tipoInput   = document.querySelector('#tipoPagoInput');
  const amountInput = document.querySelector('#paymentAmountInput');

  // Sin formulario de pago en pantalla no hay nada que hacer
  if (!typeBtns.length || !tipoInput || !amountInput) return;

  const saldo = parseFloat(amountInput.dataset.saldo || '0');

  typeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      typeBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      tipoInput.value = btn.dataset.tipo;

      if (btn.dataset.tipo === 'Liquidación') {
        amountInput.value = saldo.toFixed(2);   // la liquidación cubre todo el saldo
      } else {
        amountInput.value = '';
        amountInput.focus();
      }
    });
  });
});