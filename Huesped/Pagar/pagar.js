/* Hotel Costa Azul · Huesped/Pagar/pagar.js   (I-07b Pagar mi reservación)
   CU-04 Gestionar pagos (A2 de CU-01). Pantalla pública: el huésped usa su número de confirmación.

   - "Buscar" ................... RF-11  (total, pagado y saldo pendiente: CALCULADOS)
   - Anticipo / Liquidación ..... RF-10 / RF-16  (PAGO.tipo_pago)
   - "Pagar" .................... RF-16  (pasarela) + RF-10 (registro en PAGO, canal "Pasarela en línea")
                                  RNF-07 (el pago queda de solo lectura) · RNF-08 (fecha y hora automáticas)
                                  RN-04  (asociado a una reservación existente)
   - Pago rechazado ............. A1     "No se pudo procesar el pago. Revisa los datos."

   Los datos de la tarjeta NUNCA se guardan (los procesa la pasarela, modelo E2).
   PASARELA SIMULADA: acepta tarjetas válidas (algoritmo de Luhn, ej. 4242 4242 4242 4242);
   rechaza cualquier número que contenga "0000" (ej. 4000 0000 0000 0002) para demostrar el flujo alterno A1.
*/
document.addEventListener('DOMContentLoaded', () => {
  const U = DB.util;
  const searchForm = document.querySelector('#searchReservationForm');
  const codeInput = document.querySelector('#reservationCode');
  const searchError = document.querySelector('#searchError');
  const summaryCard = document.querySelector('#summaryCard');
  const typeBtns = document.querySelectorAll('.type-btn');
  const amountInput = document.querySelector('#amountInput');
  const summaryTotalPay = document.querySelector('#summaryTotalPay');
  const btnPayAmountText = document.querySelector('#btnPayAmountText');
  const btnPay = document.querySelector('#btnPay');
  const paymentForm = document.querySelector('#paymentForm');
  const errorBox = document.querySelector('#paymentErrorAlert');
  const errorText = document.querySelector('#paymentErrorText');
  const okBox = document.querySelector('#paymentOkAlert');
  const okText = document.querySelector('#paymentOkText');

  let reservacion = null;
  let resumen = null;
  let tipoPago = 'Anticipo';

  const dinero = (n) => U.fmtMoneda(n, true);
  const leerMonto = () => Math.round((parseFloat(String(amountInput.value).replace(/[$,\s]/g, '')) || 0) * 100) / 100;
  const ocultarMensajes = () => { errorBox.style.display = 'none'; okBox.style.display = 'none'; };
  function mostrarError(texto) { okBox.style.display = 'none'; errorText.textContent = texto || 'No se pudo procesar el pago. Revisa los datos.'; errorBox.style.display = 'flex'; }

  function pintarMonto() {
    const m = leerMonto();
    summaryTotalPay.textContent = dinero(m);
    btnPayAmountText.textContent = dinero(m);
  }

  // RF-11: pinta el resumen financiero (todo calculado a partir de RESERVACION, TARIFA y PAGO)
  function pintarResumen() {
    const huesped = DB.usuarioPorId(reservacion.id_huesped);
    const hab = DB.habitaciones().find((h) => h.numero_habitacion === reservacion.numero_habitacion);
    resumen = DB.resumenFinanciero(reservacion);
    document.querySelector('#resGuest').textContent = huesped ? huesped.nombre : '—';
    document.querySelector('#resInfo').textContent =
      `${U.fmtFecha(reservacion.fecha_entrada)} → ${U.fmtFecha(reservacion.fecha_salida)} · ${hab ? hab.tipo_habitacion : ''}`;
    document.querySelector('#resTotal').textContent = U.fmtMoneda(resumen.total);
    document.querySelector('#resPaid').textContent = U.fmtMoneda(resumen.pagado);
    document.querySelector('#displayPendingBalance').textContent = U.fmtMoneda(resumen.saldo);
    summaryCard.hidden = false;
    btnPay.disabled = resumen.saldo <= 0 || reservacion.estado !== 'Activa';
  }

  function aplicarTipo() {
    typeBtns.forEach((b) => b.classList.toggle('active', b.getAttribute('data-type') === (tipoPago === 'Anticipo' ? 'anticipo' : 'liquidacion')));
    if (tipoPago === 'Liquidación' && resumen) {
      amountInput.value = resumen.saldo.toFixed(2);   // liquidar = pagar el saldo completo
      amountInput.readOnly = true;
    } else {
      amountInput.readOnly = false;
    }
    pintarMonto();
  }

  // RF-11: localizar la reservación
  function buscar(codigo) {
    ocultarMensajes();
    reservacion = DB.buscarPorConfirmacion(codigo);
    if (!reservacion) {
      resumen = null; summaryCard.hidden = true; btnPay.disabled = true;
      searchError.textContent = 'No encontramos una reservación con ese código.';
      searchError.hidden = false; return;
    }
    searchError.hidden = true;
    pintarResumen();
    if (reservacion.estado !== 'Activa') {
      searchError.textContent = `Esta reservación está ${reservacion.estado.toLowerCase()}; no admite pagos.`;
      searchError.hidden = false;
    } else if (resumen.saldo <= 0) {
      okText.textContent = 'Esta reservación ya está liquidada. No hay saldo pendiente.';
      okBox.style.display = 'block';
    }
    aplicarTipo();
  }

  searchForm.addEventListener('submit', (e) => { e.preventDefault(); buscar(codeInput.value); });

  // Tipo de pago: Anticipo (monto libre) / Liquidación (saldo completo)
  typeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      tipoPago = btn.getAttribute('data-type') === 'liquidacion' ? 'Liquidación' : 'Anticipo';
      if (tipoPago === 'Anticipo') amountInput.value = '';
      aplicarTipo();
    });
  });
  amountInput.addEventListener('input', pintarMonto);

  /* ---------- Pasarela de pagos SIMULADA (RF-16) ---------- */
  function luhn(num) {
    let suma = 0, doble = false;
    for (let i = num.length - 1; i >= 0; i--) {
      let d = Number(num[i]);
      if (doble) { d *= 2; if (d > 9) d -= 9; }
      suma += d; doble = !doble;
    }
    return suma % 10 === 0;
  }
  function pasarela(tarjeta) {
    const numero = tarjeta.numero.replace(/\s/g, '');
    if (!tarjeta.nombre.trim()) return false;
    if (!/^\d{13,19}$/.test(numero) || !luhn(numero)) return false;
    const m = tarjeta.vence.match(/^(\d{2})\s*\/\s*(\d{2})$/);
    if (!m || Number(m[1]) < 1 || Number(m[1]) > 12) return false;
    const aaaamm = 2000 + Number(m[2]) + '-' + m[1];
    if (aaaamm < DB.FECHA_DEMO.slice(0, 7)) return false;        // tarjeta vencida
    if (!/^\d{3,4}$/.test(tarjeta.cvv)) return false;
    if (numero.indexOf('0000') !== -1) return false;              // rechazo simulado
    return true;
  }

  /* ---------- Botón "Pagar" ---------- */
  paymentForm.addEventListener('submit', (e) => {
    e.preventDefault();
    ocultarMensajes();
    if (!reservacion) { mostrarError('Primero busca tu reservación con el código de confirmación.'); return; }

    const monto = leerMonto();
    if (!(monto > 0)) { mostrarError('Escribe el monto a pagar.'); return; }
    if (monto > resumen.saldo + 0.001) { mostrarError(`El monto no puede ser mayor al saldo pendiente (${dinero(resumen.saldo)}).`); return; }

    const tarjeta = {
      nombre: document.querySelector('#cardName').value,
      numero: document.querySelector('#cardNumber').value,
      vence: document.querySelector('#cardExp').value,
      cvv: document.querySelector('#cardCvv').value
    };
    if (!pasarela(tarjeta)) { mostrarError(); return; }            // A1: pago rechazado, se puede reintentar

    const r = DB.registrarPago({ codigo: reservacion.numero_confirmacion, monto: monto, tipo_pago: tipoPago, canal_pago: 'Pasarela en línea' });
    if (!r.ok) { mostrarError(); return; }

    // Datos de tarjeta fuera de pantalla: no se almacenan en ningún lado
    ['#cardName', '#cardNumber', '#cardExp', '#cardCvv'].forEach((s) => { document.querySelector(s).value = ''; });
    amountInput.value = '';
    pintarResumen();
    tipoPago = 'Anticipo'; aplicarTipo();
    okText.textContent = `Pago registrado correctamente. El saldo pendiente ahora es ${U.fmtMoneda(r.resumen.saldo, true)}.`;
    okBox.style.display = 'block';
  });

  // Si llega desde la confirmación (Reser) con ?codigo=XXXXX, se busca de inmediato
  const codigo = new URLSearchParams(window.location.search).get('codigo');
  if (codigo) { codeInput.value = codigo.toUpperCase(); buscar(codigo); }
});
