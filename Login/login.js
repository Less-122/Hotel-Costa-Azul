/* Hotel Costa Azul · Login/login.js  (I-01 Inicio de sesión del PERSONAL)
   RNF-09: acceso exclusivo con credenciales. RNF-03 / RNF-08: la sesión identifica
   al recepcionista que registra reservaciones y pagos.
   El huésped NO inicia sesión: reserva, paga y cancela con su número de confirmación. */
document.addEventListener('DOMContentLoaded', () => {
  const togglePassword = document.querySelector('#togglePassword');
  const passwordInput = document.querySelector('#password');
  const loginForm = document.querySelector('#loginForm');
  const loginError = document.querySelector('#loginError');

  // Mostrar / ocultar contraseña
  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
      togglePassword.classList.toggle('fa-eye');
      togglePassword.classList.toggle('fa-eye-slash');
    });
  }

  // Botón "Iniciar sesión" (RNF-09): valida credenciales y redirige según el rol
  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const resultado = DB.iniciarSesion(document.querySelector('#username').value, passwordInput.value);
    if (!resultado.ok) {
      loginError.hidden = false;          // "Revisa usuario o contraseña."
      passwordInput.value = '';
      passwordInput.focus();
      return;
    }
    loginError.hidden = true;
    window.location.href = resultado.destino; // Admin → Reportes · Recepcionista → Reservaciones · Camarista → Mis habitaciones
  });

  // Al escribir de nuevo se limpia el aviso
  loginForm.addEventListener('input', () => { loginError.hidden = true; });
});
