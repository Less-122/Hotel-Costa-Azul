let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');

// Función para cambiar las diapositivas del carrusel automáticamente o mediante botones
function moveSlide(direction) {
  slides[currentSlide].classList.remove('active');
  
  currentSlide = (currentSlide + direction + slides.length) % slides.length;
  
  slides[currentSlide].classList.add('active');
}

// Rotación automática del carrusel cada 5 segundos
setInterval(() => {
  moveSlide(1);
}, 5000);

// Funciones para abrir y cerrar el Modal de Políticas del Hotel
function openPolicyModal() {
  document.getElementById('policyModal').style.display = 'flex';
}

function closePolicyModal() {
  document.getElementById('policyModal').style.display = 'none';
}