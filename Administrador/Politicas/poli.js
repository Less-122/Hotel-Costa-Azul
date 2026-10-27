document.addEventListener("DOMContentLoaded", () => {
    const policyForm = document.getElementById("policyForm");
    const policiesTableBody = document.getElementById("policiesTableBody");

    policyForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const description = document.getElementById("policyDesc").value.trim();
        const startDateInput = document.getElementById("startDate").value;

        if (!startDateInput || !description) return;

        // Formatear la fecha
        const dateObj = new Date(startDateInput + "T00:00:00");
        const options = { day: '2-digit', month: 'short', year: 'numeric' };
        const formattedStartDate = dateObj.toLocaleDateString('es-ES', options).replace('.', '');

        // Cambiar la política vigente actual a 'Histórica'
        const rows = policiesTableBody.querySelectorAll("tr");
        rows.forEach(row => {
            const badgeCell = row.cells[3];
            
            if (badgeCell.querySelector(".badge-vigente")) {
                row.classList.remove("row-highlight");
                row.cells[2].textContent = formattedStartDate; // Hasta
                badgeCell.innerHTML = '<span class="badge badge-historica">Histórica</span>';
            }
        });

        // Crear la nueva fila de política vigente
        const newRow = document.createElement("tr");
        newRow.classList.add("row-highlight");
        newRow.innerHTML = `
            <td class="desc-cell">${description}</td>
            <td>${formattedStartDate}</td>
            <td>—</td>
            <td><span class="badge badge-vigente">Vigente</span></td>
        `;

        // Insertar al inicio de la tabla
        policiesTableBody.insertBefore(newRow, policiesTableBody.firstChild);

        // Limpiar formulario
        policyForm.reset();
        alert("¡Nueva política de cancelación guardada con éxito!");
    });
});