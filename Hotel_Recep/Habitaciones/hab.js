document.addEventListener('DOMContentLoaded', () => {
  const roomRows = document.querySelectorAll('.room-row');
  const selectedRoomTitle = document.querySelector('#selectedRoomTitle');
  const selectedRoomType = document.querySelector('#selectedRoomType');
  const roomUpdateForm = document.querySelector('#roomUpdateForm');

  // Permitir seleccionar filas de la tabla para cargar los datos en el panel derecho
  roomRows.forEach(row => {
    row.addEventListener('click', () => {
      roomRows.forEach(r => r.classList.remove('selected'));
      row.classList.add('selected');

      const roomNum = row.getAttribute('data-room');
      const roomType = row.getAttribute('data-type');
      const cleanState = row.getAttribute('data-clean');
      const dispState = row.getAttribute('data-disp');

      // Actualizar el título del panel derecho
      selectedRoomTitle.textContent = `Habitación ${roomNum}`;
      selectedRoomType.textContent = roomType;

      // Actualizar los radio buttons correspondientes
      document.querySelectorAll('input[name="cleanStatus"]').forEach(radio => {
        radio.checked = (radio.value === cleanState);
        updateRadioStyle(radio);
      });

      document.querySelectorAll('input[name="dispStatus"]').forEach(radio => {
        radio.checked = (radio.value === dispState);
        updateRadioStyle(radio);
      });
    });
  });

  // Función auxiliar para actualizar estilos visuales de los radios activos
  function updateRadioStyle(radio) {
    const parentLabel = radio.closest('.radio-option');
    if (radio.checked) {
      parentLabel.classList.add('active');
    } else {
      parentLabel.classList.remove('active');
    }
  }

  // Guardar cambios del formulario de habitación
  roomUpdateForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const roomName = selectedRoomTitle.textContent;
    alert(`¡Cambios guardados para la ${roomName}! La lista de la camarista ha sido actualizada.`);
  });
});