const slides = document.querySelectorAll('.carousel-slide');
let currentSlide = 0;

function moveSlide(direction) {
  if (slides.length < 2) return;
  slides[currentSlide].classList.remove('active');
  currentSlide = (currentSlide + direction + slides.length) % slides.length;
  slides[currentSlide].classList.add('active');
}

// Rotación automática cada 5 segundos
if (slides.length > 1) {
  setInterval(() => moveSlide(1), 5000);
}

function openPolicyModal()  { document.getElementById('policyModal').style.display = 'flex'; }
function closePolicyModal() { document.getElementById('policyModal').style.display = 'none'; }