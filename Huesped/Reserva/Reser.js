document.addEventListener('DOMContentLoaded', () => {
  const roomCards = document.querySelectorAll('.room-card');
  const summaryRoomText = document.querySelector('#summaryRoomText');
  const totalPrice = document.querySelector('#totalPrice');
  const reservationForm = document.querySelector('#reservationForm');
  
  // Elementos de los Modales
  const roomModal = document.querySelector('#roomModal');
  const closeModalBtn = document.querySelector('#closeModalBtn');
  
  const successModal = document.querySelector('#successModal');
  const btnReady = document.querySelector('#btnReady');
  const btnCancelRes = document.querySelector('#btnCancelRes');
  const btnPayNow = document.querySelector('#btnPayNow');
  
  const successSubtitle = document.querySelector('#successSubtitle');
  const modalRoomDetail = document.querySelector('#modalRoomDetail');
  const modalTotalDetail = document.querySelector('#modalTotalDetail');

  let selectedRoomName = "Habitación sencilla · 2 noches";
  let selectedTotalVal = "$2,400";

  // Selección de habitaciones
  roomCards.forEach(card => {
    card.addEventListener('click', () => {
      roomCards.forEach(c => {
        c.classList.remove('selected');
        const btn = c.querySelector('.btn-select-room');
        btn.classList.remove('selected-btn');
        btn.innerHTML = 'Elegir';
      });

      card.classList.add('selected');
      const activeBtn = card.querySelector('.btn-select-room');
      activeBtn.classList.add('selected-btn');
      activeBtn.innerHTML = '<i class="fa-solid fa-check"></i> Elegida';

      const roomName = card.querySelector('h2').textContent;
      const roomPrice = card.getAttribute('data-price');
      
      selectedRoomName = `${roomName} · 2 noches`;
      summaryRoomText.textContent = selectedRoomName;

      const calculatedTotal = parseInt(roomPrice) * 2;
      selectedTotalVal = `$${calculatedTotal.toLocaleString('en-US')}`;
      totalPrice.textContent = selectedTotalVal;
    });
  });

  // Al hacer clic en "Reservar ahora"
  reservationForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const guestName = document.querySelector('#guestName').value.trim() || "Ana";

    // SIMULACIÓN: Puedes cambiar esto para probar el modal de éxito o el de error
    const isAvailable = true; // Cambia a false si quieres probar cuando la habitación se ocupa

    if (isAvailable) {
      // Actualizamos los datos dinámicos en el modal de éxito
      successSubtitle.textContent = `Gracias, ${guestName}. Te esperamos en Costa Azul.`;
      modalRoomDetail.textContent = selectedRoomName;
      modalTotalDetail.textContent = selectedTotalVal;

      // Mostramos el modal de éxito
      successModal.style.display = 'flex';
    } else {
      // Mostramos el modal de error (habitación ocupada)
      roomModal.style.display = 'flex';
    }
  });

  // Cerrar modal de habitación ocupada
  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
      roomModal.style.display = 'none';
    });
  }

  // Botones del modal de éxito
  if (btnReady) {
    btnReady.addEventListener('click', () => {
      successModal.style.display = 'none';
    });
  }

  if (btnCancelRes) {
    btnCancelRes.addEventListener('click', () => {
      if (confirm('¿Estás seguro de que deseas cancelar esta reservación?')) {
        successModal.style.display = 'none';
      }
    });
  }

  if (btnPayNow) {
    btnPayNow.addEventListener('click', () => {
      alert('Redirigiendo a la pasarela de pago...');
      // Aquí puedes redirigir a tu interfaz de pago si la tienes conectada
    });
  }
});