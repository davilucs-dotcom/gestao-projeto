'use strict';

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: { legend: { position: 'bottom' } }
};

function chartData(canvas, name) {
    try {
        return JSON.parse(canvas.dataset[name]);
    } catch (error) {
        console.error(`Dados invalidos para o grafico ${canvas.id}.`, error);
        return null;
    }
}

if (window.Chart) {
    const modalityCanvas = document.getElementById('modalityChart');
    if (modalityCanvas) {
        const labels = chartData(modalityCanvas, 'labels');
        const values = chartData(modalityCanvas, 'values');
        if (labels && values) {
            new Chart(modalityCanvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#176b48', '#68a679', '#9cafa2'],
                        borderWidth: 0
                    }]
                },
                options: { ...chartDefaults, cutout: '68%' }
            });
        }
    }

    const reportCanvas = document.getElementById('reportStatusChart');
    if (reportCanvas) {
        const labels = chartData(reportCanvas, 'labels');
        const values = chartData(reportCanvas, 'values');
        const colors = chartData(reportCanvas, 'colors');
        if (labels && values && colors) {
            new Chart(reportCanvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Relatórios',
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    ...chartDefaults,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } }
                }
            });
        }
    }
}
