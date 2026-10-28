/* Hotel Costa Azul · Huesped/Cancelar/Cance.js   (I-03 Cancelar reservación)
   CU-01 Gestionar reservación (cancelación). Pantalla pública: se localiza con el número de confirmación.

   - "Buscar" ................... RF-13  (localiza por numero_confirmacion)
   - "Cancelar reservación" ..... RF-13 / RF-09 (abre la ventana de confirmación)
   - "Sí, cancelar" ............. RF-09  (estado = "Cancelada"; la habitación queda libre, CU-06 A1)
*/
document.addEventListener('DOMContentLoaded', () => {
  const U = DB.util;
  const form = document.querySelector('#cancelSearchForm');
  const confCodeInput = document.querySelector('#confCode');
  const searchError = document.querySelector('#searchError');
  const resultBox = document.querySelector('#reservationResult');
  const statusBadge = document.querySelector('#statusBadge');
  const policyText = document.querySelector('#policyText');
  const confirmModal = document.querySelector('#confirmModal');
  const openModalBtn = document.querySelector('#openModalBtn');
  const btnNoReturn = document.querySelector('#btnNoReturn');
  const btnYesCancel = document.querySelector('#btnYesCancel');

  let reservacion = null;

  function pintarEstado() {
    const activa = reservacion && reservacion.estado === 'Activa';
    statusBadge.textContent = reservacion.estado;
    statusBadge.className = activa ? 'badge-active' : (reservacion.estado === 'Cancelada' ? 'badge-cancelled' : 'badge-active');
    openModalBtn.disabled = !activa;
    openModalBtn.textContent = activa ? 'Cancelar reservación'
      : (reservacion.estado === 'Cancelada' ? 'Reservación cancelada' : 'Reservación finalizada');
  }

  // RF-13: localizar la reservación
  function buscar(codigo) {
    reservacion = DB.buscarPorConfirmacion(codigo);
    if (!reservacion) {
      resultBox.hidden = true; openModalBtn.disabled = true;
      searchError.hidden = false;                       // "No encontramos una reservación con ese código."
      policyText.textContent = 'Escribe tu código para ver la política de cancelación de tu reservación.';
      return;
    }
    searchError.hidden = true;
    const huesped = DB.usuarioPorId(reservacion.id_huesped);
    const hab = DB.habitaciones().find((h) => h.numero_habitacion === reservacion.numero_habitacion);
    document.querySelector('#displayCode').textContent = reservacion.numero_confirmacion;
    document.querySelector('#resGuest').textContent = huesped ? huesped.nombre : '—';
    document.querySelector('#resDates').textContent = `${U.fmtFechaLarga(reservacion.fecha_entrada)} al ${U.fmtFechaLarga(reservacion.fecha_salida)}`;
    document.querySelector('#resRoom').textContent = `${reservacion.numero_habitacion} · ${hab ? hab.tipo_habitacion : ''}`;
    document.querySelector('#resTotal').textContent = U.fmtMoneda(DB.totalReservacion(reservacion), true);
    const pol = DB.politicaDe(reservacion);               // la política vigente CUANDO se hizo la reserva
    policyText.textContent = pol ? `Política de tu reservación: ${pol.descripcion}` : '';
    resultBox.hidden = false;
    pintarEstado();
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    buscar(confCodeInput.value);
  });

  // "Cancelar reservación": ventana "¿Cancelar esta reservación?"
  openModalBtn.addEventListener('click', () => { if (reservacion) confirmModal.style.display = 'flex'; });
  // "No, volver": cierra sin cancelar
  btnNoReturn.addEventListener('click', () => { confirmModal.style.display = 'none'; });

  // "Sí, cancelar" (RF-09): cambia el estado a Cancelada
  btnYesCancel.addEventListener('click', () => {
    confirmModal.style.display = 'none';
    const r = DB.cancelarReservacion(reservacion.numero_confirmacion);
    if (!r.ok) { buscar(reservacion.numero_confirmacion); return; }
    reservacion = r.reservacion;
    pintarEstado();
    let aviso = document.querySelector('#cancelOk');
    if (!aviso) {
      aviso = document.createElement('p');
      aviso.id = 'cancelOk'; aviso.className = 'success-note'; aviso.setAttribute('role', 'status');
      resultBox.after(aviso);
    }
    aviso.textContent = 'Reservación cancelada. La habitación quedó liberada.';
  });

  // Si llega desde la confirmación (Reser) con ?codigo=XXXXX, se busca de inmediato
  const codigo = new URLSearchParams(window.location.search).get('codigo');
  if (codigo) { confCodeInput.value = codigo.toUpperCase(); buscar(codigo); }
});
