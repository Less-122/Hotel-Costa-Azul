/* Hotel Costa Azul · Huesped/Reserva/Reser.js   (I-02 Reservar en línea + I-02b Confirmación)
   CU-01 Gestionar reservación (incluye CU-03; extendido por CU-02 y CU-04).
   Pantalla pública: el huésped NO inicia sesión. canal = "En línea", id_recepcionista = null.

   Trazabilidad de cada botón:
   - "Buscar disponibilidad" ........ RF-04, RF-05  (disponibilidad por fechas, calculada)
   - Tarjeta "Elegir" ............... RF-12        (tipo de habitación)
   - "Reservar ahora" ............... RF-01, RF-12, RF-02, RF-06 · RNF-01, RNF-02, RNF-04 · RN-03
   - Modal "Pagar ahora" ............ RF-16 → CU-04 (Huesped/Pagar)
   - Modal "Cancelar" ............... RF-13 → CU-01 cancelación (Huesped/Cancelar)
*/
document.addEventListener('DOMContentLoaded', () => {
  const U = DB.util;

  const entradaInput = document.querySelector('#fechaEntrada');
  const salidaInput = document.querySelector('#fechaSalida');
  const btnAvailability = document.querySelector('#btnAvailability');
  const dateError = document.querySelector('#dateError');
  const roomsList = document.querySelector('#roomsList');
  const noRooms = document.querySelector('#noRooms');

  const reservationForm = document.querySelector('#reservationForm');
  const guestName = document.querySelector('#guestName');
  const guestPhone = document.querySelector('#guestPhone');
  const guestNotes = document.querySelector('#guestNotes');
  const formError = document.querySelector('#formError');
  const summaryRoomText = document.querySelector('#summaryRoomText');
  const summaryDates = document.querySelector('#summaryDates');
  const totalPrice = document.querySelector('#totalPrice');

  const roomModal = document.querySelector('#roomModal');
  const closeModalBtn = document.querySelector('#closeModalBtn');
  const successModal = document.querySelector('#successModal');
  const btnReady = document.querySelector('#btnReady');
  const btnCancelRes = document.querySelector('#btnCancelRes');
  const btnPayNow = document.querySelector('#btnPayNow');

  const FOTOS = {
    Sencilla: 'https://images.unsplash.com/photo-1591088398332-8a7791972843?q=80&w=600&auto=format&fit=crop',
    Doble: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=600&auto=format&fit=crop'
  };

  let tipoElegido = null;   // 'Sencilla' | 'Doble'
  let ultimaReserva = null; // reservación recién guardada (para Pagar / Cancelar)

  // Fechas iniciales: la fecha de la demostración y dos noches
  entradaInput.min = DB.FECHA_DEMO;
  entradaInput.value = DB.FECHA_DEMO;
  salidaInput.value = U.sumarDias(DB.FECHA_DEMO, 2);
  salidaInput.min = U.sumarDias(DB.FECHA_DEMO, 1);

  function mostrar(el, texto) { el.textContent = texto; el.hidden = false; }
  function ocultar(el) { el.hidden = true; }

  /* ---------- RF-04 / RF-05: consultar y mostrar disponibilidad ---------- */
  function actualizarResumen() {
    const entrada = entradaInput.value, salida = salidaInput.value;
    if (!tipoElegido || DB.validarFechas(entrada, salida)) {
      summaryRoomText.textContent = 'Elige una habitación';
      summaryDates.textContent = '—';
      totalPrice.textContent = '$0';
      return;
    }
    const n = U.noches(entrada, salida);
    const tarifa = DB.tarifaVigente(tipoElegido);
    summaryRoomText.textContent = `Habitación ${tipoElegido.toLowerCase()} · ${n} noche${n === 1 ? '' : 's'}`;
    summaryDates.textContent = `${U.fmtFecha(entrada)} → ${U.fmtFecha(salida)}`;
    totalPrice.textContent = U.fmtMoneda(n * tarifa.precio_noche); // total calculado, no se guarda
  }

  function buscarDisponibilidad() {
    ocultar(dateError); ocultar(formError);
    const entrada = entradaInput.value, salida = salidaInput.value;
    const error = DB.validarFechas(entrada, salida);
    if (error) {
      roomsList.innerHTML = ''; noRooms.hidden = true; tipoElegido = null; actualizarResumen();
      mostrar(dateError, error === 'campos' ? 'Completa los campos obligatorios para continuar.'
        : error === 'pasado' ? 'La fecha de entrada no puede ser anterior a hoy.'
        : 'La salida debe ser después de la entrada.');
      return;
    }
    const n = U.noches(entrada, salida);
    const tipos = DB.disponibilidadPorTipo(entrada, salida);
    if (tipoElegido && !tipos.some((t) => t.tipo_habitacion === tipoElegido && t.libres > 0)) tipoElegido = null;

    roomsList.innerHTML = tipos.map((t) => {
      const libre = t.libres > 0;
      const elegida = libre && t.tipo_habitacion === tipoElegido;
      return `
        <div class="room-card${elegida ? ' selected' : ''}${libre ? '' : ' disabled'}" data-tipo="${t.tipo_habitacion}">
          <img src="${FOTOS[t.tipo_habitacion]}" alt="Habitación ${t.tipo_habitacion.toLowerCase()}">
          <div class="room-details">
            <h2>Habitación ${t.tipo_habitacion.toLowerCase()}</h2>
            <span class="badge ${libre ? 'available' : 'unavailable'}">${libre ? 'Disponible' : 'No disponible'}</span>
            <p>${n} noche${n === 1 ? '' : 's'} · ${U.fmtFecha(entrada)} al ${U.fmtFecha(salida)}</p>
          </div>
          <div class="room-pricing">
            <span class="price">${U.fmtMoneda(t.precio_noche)}</span>
            <span class="period">por noche</span>
            <button type="button" class="btn-select-room${elegida ? ' selected-btn' : ''}"${libre ? '' : ' disabled'}>
              ${elegida ? '<i class="fa-solid fa-check"></i> Elegida' : 'Elegir'}
            </button>
          </div>
        </div>`;
    }).join('');
    noRooms.hidden = tipos.some((t) => t.libres > 0); // "No hay habitaciones disponibles en esas fechas."
    actualizarResumen();
  }

  btnAvailability.addEventListener('click', buscarDisponibilidad);
  entradaInput.addEventListener('change', () => {
    // la salida mínima siempre es el día siguiente a la entrada
    if (entradaInput.value) {
      salidaInput.min = U.sumarDias(entradaInput.value, 1);
      if (!salidaInput.value || salidaInput.value <= entradaInput.value) salidaInput.value = U.sumarDias(entradaInput.value, 1);
    }
    buscarDisponibilidad();
  });
  salidaInput.addEventListener('change', buscarDisponibilidad);

  /* ---------- RF-12: elegir tipo de habitación ---------- */
  roomsList.addEventListener('click', (e) => {
    const card = e.target.closest('.room-card');
    if (!card || card.classList.contains('disabled')) return;
    tipoElegido = card.getAttribute('data-tipo');
    ocultar(formError);
    buscarDisponibilidad();
  });

  /* ---------- RF-01 / RF-12 / RF-02 / RF-06: botón "Reservar ahora" ---------- */
  reservationForm.addEventListener('submit', (e) => {
    e.preventDefault();
    ocultar(formError);

    // RNF-01: no se continúa con campos obligatorios vacíos o inválidos
    const nombre = guestName.value.trim(), telefono = guestPhone.value.trim();
    if (!nombre || !telefono || !tipoElegido || !entradaInput.value || !salidaInput.value) {
      mostrar(formError, 'Completa los campos obligatorios para continuar.'); return;
    }
    if (!/^\d{10}$/.test(telefono)) { mostrar(formError, 'El teléfono debe tener 10 dígitos.'); return; }

    const resultado = DB.crearReservacion({
      nombre, telefono, tipo: tipoElegido, notas: guestNotes.value,
      entrada: entradaInput.value, salida: salidaInput.value, canal: 'En línea'
    });

    if (!resultado.ok) {
      if (resultado.error === 'ocupada') {          // A3 · RNF-04 / RN-03: choque de concurrencia
        roomModal.style.display = 'flex';
      } else {
        mostrar(formError, resultado.error === 'campos' ? 'Completa los campos obligatorios para continuar.'
          : 'No se pudo guardar la reservación. Revisa las fechas e intenta de nuevo.');
      }
      return;
    }

    // I-02b Confirmación (CU-03): código de 5 caracteres, habitación, fechas y total
    ultimaReserva = resultado.reservacion;
    const n = U.noches(ultimaReserva.fecha_entrada, ultimaReserva.fecha_salida);
    document.querySelector('#successSubtitle').textContent = `Gracias, ${nombre.split(' ')[0]}. Te esperamos en Costa Azul.`;
    document.querySelector('#confCode').textContent = ultimaReserva.numero_confirmacion;
    document.querySelector('#modalRoomDetail').textContent = `${tipoElegido} · ${n} noche${n === 1 ? '' : 's'}`;
    document.querySelector('#modalDatesDetail').textContent = `${U.fmtFecha(ultimaReserva.fecha_entrada)} → ${U.fmtFecha(ultimaReserva.fecha_salida)}`;
    document.querySelector('#modalTotalDetail').textContent = U.fmtMoneda(resultado.total);
    successModal.style.display = 'flex';
  });

  /* ---------- A3: "Elegir otra habitación" (la disponibilidad se recalcula) ---------- */
  closeModalBtn.addEventListener('click', () => {
    roomModal.style.display = 'none';
    tipoElegido = null;
    buscarDisponibilidad();
  });

  /* ---------- Botones del modal de confirmación ---------- */
  // "Listo": termina el flujo principal y regresa al inicio
  btnReady.addEventListener('click', () => { window.location.href = '../../Principal/index.html'; });
  // "Cancelar" (RF-13): abre I-03 con el código ya escrito; ahí se confirma
  btnCancelRes.addEventListener('click', () => {
    window.location.href = `../Cancelar/Cance.html?codigo=${ultimaReserva.numero_confirmacion}`;
  });
  // "Pagar ahora" (RF-16 / CU-04, A2 de CU-01): abre I-07b con el código ya escrito
  btnPayNow.addEventListener('click', () => {
    window.location.href = `../Pagar/pagar.html?codigo=${ultimaReserva.numero_confirmacion}`;
  });

  buscarDisponibilidad();
});
