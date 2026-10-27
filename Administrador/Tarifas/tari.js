document.addEventListener("DOMContentLoaded", () => {
    const rateForm = document.getElementById("rateForm");
    const ratesTableBody = document.getElementById("ratesTableBody");

    rateForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const roomType = document.getElementById("roomType").value;
        const price = parseFloat(document.getElementById("roomPrice").value).toFixed(2);
        const startDateInput = document.getElementById("startDate").value;

        if (!startDateInput) return;

        // Formatear la fecha
        const dateObj = new Date(startDateInput + "T00:00:00");
        const options = { day: '2-digit', month: 'short', year: 'numeric' };
        const formattedStartDate = dateObj.toLocaleDateString('es-ES', options).replace('.', '');

        // Cambiar la tarifa actual del mismo tipo a 'Histórica'
        const rows = ratesTableBody.querySelectorAll("tr");
        rows.forEach(row => {
            const typeCell = row.cells[0].textContent.trim();
            const badgeCell = row.cells[4];
            
            if (typeCell === roomType && badgeCell.querySelector(".badge-vigente")) {
                row.classList.remove("row-highlight");
                row.cells[3].textContent = formattedStartDate; // Hasta
                badgeCell.innerHTML = '<span class="badge badge-historica">Histórica</span>';
            }
        });

        // Crear la nueva fila de tarifa vigente
        const newRow = document.createElement("tr");
        newRow.classList.add("row-highlight");
        newRow.innerHTML = `
            <td><strong>${roomType}</strong></td>
            <td>$${price}</td>
            <td>${formattedStartDate}</td>
            <td>—</td>
            <td><span class="badge badge-vigente">Vigente</span></td>
        `;

        // Insertar al inicio de la tabla
        ratesTableBody.insertBefore(newRow, ratesTableBody.firstChild);

        // Limpiar formulario
        rateForm.reset();
        alert(`¡Nueva tarifa para habitación ${roomType} guardada con éxito!`);
    });
});