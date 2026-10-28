/* Hotel Costa Azul · Principal/index.js
   Pantalla pública (sin sesión). Solo LEE la BD: tarifas (RF-14) y política vigente (RF-14). */

let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');

// Cambia las diapositivas del carrusel (botones o automático)
function moveSlide(direction) {
  slides[currentSlide].classList.remove('active');
  currentSlide = (currentSlide + direction + slides.length) % slides.length;
  slides[currentSlide].classList.add('active');
}

// Rotación automática cada 5 segundos
setInterval(() => moveSlide(1), 5000);

// RF-14: los precios vienen de TARIFA (la vigente de cada tipo), no están escritos a mano.
document.querySelectorAll('[data-tarifa]').forEach((el) => {
  const t = DB.tarifaVigente(el.getAttribute('data-tarifa'));
  el.textContent = t ? DB.util.fmtMoneda(t.precio_noche) : 'Consultar';
});

// RF-14: la política de cancelación mostrada es POLITICA_CANCELACION vigente.
function openPolicyModal() {
  const p = DB.politicaVigente();
  document.getElementById('policyText').textContent = p ? p.descripcion : 'Consulta las políticas en recepción.';
  document.getElementById('policyModal').style.display = 'flex';
}

function closePolicyModal() {
  document.getElementById('policyModal').style.display = 'none';
}
