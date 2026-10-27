document.addEventListener('DOMContentLoaded', () => {

  // ... (tus funciones para mostrar/ocultar contraseñas) ...

  // Manejo del envío del formulario de registro
  const registerForm = document.querySelector('#registerForm');
  registerForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const fullname = document.querySelector('#fullname').value.trim();
    const pass = document.querySelector('#password').value;
    const confirmPass = document.querySelector('#confirmPassword').value;

    if (pass.length < 8) {
      alert('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (pass !== confirmPass) {
      alert('Las contraseñas no coinciden. Por favor verifícalas.');
      return;
    }

    // 1. Mensaje de éxito
    alert(`¡Cuenta creada con éxito para ${fullname}!`);
    
    // 2. AQUÍ SE CONECTA: Esto lo manda automáticamente al login (index.html)
    window.location.href = 'login.html';
  });

});