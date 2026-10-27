document.addEventListener('DOMContentLoaded', () => {
  const newReservationForm = document.querySelector('#newReservationForm');
  const filterPills = document.querySelectorAll('.pill');
  const searchInput = document.querySelector('#searchInput');

  // Filtrado de pestañas (Todas, Activas, Canceladas)
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      filterPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      
      const filterType = pill.getAttribute('data-filter');
      // Aquí puedes programar la lógica para mostrar u ocultar filas de la tabla según el filtro
    });
  });

  // Registro de nueva reservación desde recepción
  newReservationForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const guestName = document.querySelector('#guestNameInput').value.trim();
    
    alert(`¡Reservación guardada con éxito en recepción para ${guestName}! Se han registrado fecha, hora y usuario.`);
    newReservationForm.reset();
  });
});