document.addEventListener('DOMContentLoaded', () => {
  const togglePassword = document.querySelector('#togglePassword');
  const passwordInput = document.querySelector('#password');
  const loginForm = document.querySelector('#loginForm');
  const linkRegister = document.querySelector('#linkRegister'); // Capturamos el enlace

  // Alternar la visibilidad de la contraseña
  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
      
      togglePassword.classList.toggle('fa-eye');
      togglePassword.classList.toggle('fa-eye-slash');
    });
  }

  // Evento de envío de formulario de login
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const username = document.querySelector('#username').value.trim();
      const password = passwordInput.value;

      if (username && password) {
        alert(`Intentando iniciar sesión con usuario: ${username}`);
      }
    });
  }

  // --- REDIRECCIÓN AL HACER CLIC EN "Crear cuenta" ---
  if (linkRegister) {
    linkRegister.addEventListener('click', (e) => {
      e.preventDefault(); // Evita comportamiento por defecto si fuera necesario
      window.location.href = 'regis.html'; // Lo manda a tu interfaz de registro
    });
  }
});