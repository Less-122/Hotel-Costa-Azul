document.addEventListener("DOMContentLoaded", () => {
    // Configuración de la Gráfica de Ingresos por Semana
    const ctx = document.getElementById('weeklyRevenueChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
            datasets: [{
                data: [9800, 14100, 16650, 7700],
                backgroundColor: '#2a4b7c',
                borderRadius: 4,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `$${context.raw.toLocaleString()}`
                    }
                }
            },
            scales: {
                y: {
                    display: false,
                    beginAtZero: true
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#334155' }
                }
            }
        },
        plugins: [{
            id: 'topLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets[0].data.forEach((value, index) => {
                    const meta = chart.getDatasetMeta(0);
                    const x = meta.data[index].x;
                    const y = meta.data[index].y;
                    
                    ctx.save();
                    ctx.fillStyle = '#0d2746';
                    ctx.font = 'bold 12px Segoe UI';
                    ctx.textAlign = 'center';
                    ctx.fillText(`$${value.toLocaleString()}`, x, y - 8);
                    ctx.restore();
                });
            }
        }]
    });

    // Cambiar estado de botones de filtro
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
        });
    });
});