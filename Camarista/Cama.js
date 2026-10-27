// Función para expandir o contraer las tarjetas de habitaciones al hacer clic
function toggleCard(headerElement) {
  const clickedCard = headerElement.closest('.room-card');
  const allCards = document.querySelectorAll('.room-card');

  allCards.forEach(card => {
    const body = card.querySelector('.room-card-body');
    const openLink = card.querySelector('.open-link');

    if (card === clickedCard) {
      const isExpanded = card.classList.contains('expanded');
      if (isExpanded) {
        card.classList.remove('expanded');
        card.classList.add('collapsed');
        body.style.display = 'none';
        if(openLink) openLink.style.display = 'inline';
      } else {
        card.classList.remove('collapsed');
        card.classList.add('expanded');
        body.style.display = 'block';
        if(openLink) openLink.style.display = 'none';
      }
    } else {
      card.classList.remove('expanded');
      card.classList.add('collapsed');
      body.style.display = 'none';
      const link = card.querySelector('.open-link');
      if(link) link.style.display = 'inline';
    }
  });
}

// Función para cambiar el estado de una habitación (Limpia / Sucia / Mantenimiento)
function changeRoomState(roomNum, newState) {
  const card = document.querySelector(`.room-card[data-room="${roomNum}"]`);
  const badge = document.querySelector(`#badge-${roomNum}`);
  const subtitleNotice = document.querySelector('#subtitleNotice');
  const notificationTitle = document.querySelector('#notificationTitle');

  // Actualizar texto y clases del badge
  badge.textContent = newState;
  badge.className = 'badge';

  if (newState === 'Limpia') {
    badge.classList.add('clean');
  } else if (newState === 'Sucia') {
    badge.classList.add('dirty');
  } else {
    badge.classList.add('maintenance');
  }

  // Actualizar botones activos dentro de la tarjeta
  const buttons = card.querySelectorAll('.state-btn');
  buttons.forEach(btn => {
    btn.classList.remove('active');
    if (btn.textContent.includes(newState)) {
      btn.classList.add('active');
    }
  });

  // Actualizar mensaje de la notificación inferior de éxito
  notificationTitle.textContent = `Hab. ${roomNum} marcada como ${newState}`;
  
  // Recuento dinámico de sucias restantes de ejemplo
  let dirtyCount = document.querySelectorAll('.badge.dirty').length;
  if(dirtyCount > 0) {
    subtitleNotice.textContent = `Tienes ${dirtyCount} habitaciones sucias.`;
  } else {
    subtitleNotice.textContent = `¡Excelente trabajo! No quedan habitaciones sucias.`;
  }
}