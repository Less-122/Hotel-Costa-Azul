document.addEventListener("DOMContentLoaded", function () {
    const contactoInput = document.querySelector("input[name='contacto']");
    const userForm = document.querySelector(".user-form");

    // 1. Restringir el campo de contacto para que solo acepte números y máximo 10 dígitos
    if (contactoInput) {
        contactoInput.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }

    // 2. Validación antes de enviar el formulario
    if (userForm) {
        userForm.addEventListener("submit", function (e) {
            if (contactoInput && contactoInput.value.length < 10) {
                alert("Atención: El número de contacto debe tener exactamente 10 dígitos.");
                contactoInput.focus();
                e.preventDefault(); // Detiene el envío si no cumple la regla
            }
        });
    }
});