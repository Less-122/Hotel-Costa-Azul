document.addEventListener('DOMContentLoaded', () => {
  const cancelSearchForm = document.querySelector('#cancelSearchForm');
  const confCodeInput = document.querySelector('#confCode');
  const displayCode = document.querySelector('#displayCode');
  
  // Elementos del Modal
  const confirmModal = document.querySelector('#confirmModal');
  const openModalBtn = document.querySelector('#openModalBtn');
  const btnNoReturn = document.querySelector('#btnNoReturn');
  const btnYesCancel = document.querySelector('#btnYesCancel');
  
  const statusBadge = document.querySelector('#statusBadge');

  // Buscar reservación
  cancelSearchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const codeVal = confCodeInput.value.trim().toUpperCase();
    if (codeVal) {
      displayCode.textContent = codeVal;
    }
  });

  // Abrir la ventana emergente al hacer clic en "Cancelar reservación"
  openModalBtn.addEventListener('click', () => {
    confirmModal.style.display = 'flex';
  });

  // Botón "No, volver" (cierra el modal sin cancelar)
  btnNoReturn.addEventListener('click', () => {
    confirmModal.style.display = 'none';
  });

  // Botón "Sí, cancelar" (ejecuta la cancelación y actualiza el estado visual)
  btnYesCancel.addEventListener('click', () => {
    confirmModal.style.display = 'none';
    
    // Cambia el estado visual de la tarjeta a Cancelada
    statusBadge.textContent = 'Cancelada';
    statusBadge.className = 'badge-cancelled';

    // Cambia el botón inferior para reflejar que ya fue cancelada
    openModalBtn.textContent = 'Reservación cancelada';
    openModalBtn.disabled = true;
    openModalBtn.style.backgroundColor = '#a8a29e';
    openModalBtn.style.cursor = 'default';

    alert('La reservación ha sido cancelada exitosamente y la habitación quedó liberada.');
  });
});