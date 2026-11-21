document.addEventListener('DOMContentLoaded', () => {
  const UMBRAL_SLA_MS = 3000;
  const tInicioVista  = performance.now();

  window.addEventListener('load', () => {
    const nav = performance.getEntriesByType('navigation')[0];
    const ms  = nav
      ? Math.round(nav.responseEnd - nav.startTime)
      : Math.round(performance.now() - tInicioVista);

    console.info(`[Rendimiento pagos.js] ${ms} ms | SLA <= 3000ms: ${ms <= UMBRAL_SLA_MS ? 'OK' : 'EXCEDIDO'}`);
    if (ms > UMBRAL_SLA_MS) {
      console.warn(`[SLA >3s] La carga de pagos.php tardó ${ms} ms.`);
    }
  });

  if (typeof window.fetch === 'function') {
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = async (url, options = {}) => {
      const t0  = performance.now();
      const res = await fetchOriginal(url, options);
      const ms  = Math.round(performance.now() - t0);
      if (ms > UMBRAL_SLA_MS) {
        const srvTime = res.headers.get('X-Response-Time') ?? 'N/A';
        console.warn(`[SLA >3s] ${url} tardó ${ms} ms (Servidor: ${srvTime})`);
      }
      return res;
    };
  }

  const typeBtns    = document.querySelectorAll('.type-btn');
  const tipoInput   = document.querySelector('#tipoPagoInput');
  const amountInput = document.querySelector('#paymentAmountInput');
  const paymentForm = document.querySelector('#registerPaymentForm');

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

  if (paymentForm) {
    paymentForm.addEventListener('submit', event => {
      const tEnvio = performance.now();
      const valor  = amountInput.value.trim();

      if (!/^\d+(\.\d{1,2})?$/.test(valor)) {
        event.preventDefault();
        amountInput.setCustomValidity('Ingresa un monto válido con hasta 2 decimales (ej. 1200 o 1200.50).');
        amountInput.reportValidity();
        return;
      }

      const montoNum = parseFloat(valor);
      if (Number.isNaN(montoNum) || montoNum <= 0) {
        event.preventDefault();
        amountInput.setCustomValidity('El monto debe ser mayor a $0.00.');
        amountInput.reportValidity();
        return;
      }

      if (saldo > 0 && montoNum - saldo > 0.009) {
        event.preventDefault();
        amountInput.setCustomValidity(`El monto no puede superar el saldo pendiente ($${saldo.toFixed(2)}).`);
        amountInput.reportValidity();
        return;
      }

      amountInput.setCustomValidity('');

      window.setTimeout(() => {
        const transcurrido = Math.round(performance.now() - tEnvio);
        if (transcurrido > UMBRAL_SLA_MS) {
          console.warn(`[SLA >3s] El envío de pago está tardando más de 3000 ms (${transcurrido} ms).`);
        }
      }, UMBRAL_SLA_MS);
    });

    amountInput.addEventListener('input', () => {
      amountInput.setCustomValidity('');
    });
  }
});