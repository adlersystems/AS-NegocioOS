function cssVar(name, fallback = '') {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

function withAlpha(hex, alpha) {
    const clean = hex.replace('#', '');
    const r = Number.parseInt(clean.slice(0, 2), 16);
    const g = Number.parseInt(clean.slice(2, 4), 16);
    const b = Number.parseInt(clean.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function formatMoney(value, currency) {
    return `${currency} ${new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value))}`;
}

export default function dashboardCharts({ charts, currency, labels }) {
    return {
        init() {
            const muted = cssVar('--color-on-surface-muted', '#6b7280');
            const border = cssVar('--color-border', '#e5e7eb');
            const primary = cssVar('--color-primary', '#4f46e5');
            const success = cssVar('--color-success', '#059669');
            const info = cssVar('--color-info', '#0284c7');
            const warning = cssVar('--color-warning', '#d97706');
            const danger = cssVar('--color-danger', '#e11d48');

            const palette = [primary, success, info, warning, danger, '#8b5cf6', '#14b8a6'];

            const grid = { color: withAlpha(border, 0.5) };
            const ticks = { color: muted, font: { size: 11 } };
            const tooltip = {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#f1f5f9',
                bodyColor: '#cbd5e1',
                padding: 10,
                cornerRadius: 8,
            };

            const chart = (id, type, data, options = {}) => {
                const canvas = document.getElementById(id);
                if (!canvas) {
                    return;
                }

                new Chart(canvas, {
                    type,
                    data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip },
                        ...options,
                    },
                });
            };

            chart('chart-sales-monthly', 'line', {
                labels: charts.salesMonthly.labels,
                datasets: [{
                    label: labels.sales,
                    data: charts.salesMonthly.values,
                    borderColor: primary,
                    backgroundColor: withAlpha(primary, 0.12),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: primary,
                }],
            }, {
                scales: { x: { grid: { display: false }, ticks }, y: { grid, ticks: { ...ticks, precision: 0 } } },
            });

            chart('chart-revenue-trend', 'bar', {
                labels: charts.revenueTrend.labels,
                datasets: [{
                    label: labels.revenue,
                    data: charts.revenueTrend.values,
                    backgroundColor: withAlpha(primary, 0.75),
                    hoverBackgroundColor: primary,
                    borderRadius: 5,
                    borderSkipped: false,
                }],
            }, {
                scales: {
                    x: { grid: { display: false }, ticks },
                    y: { grid, ticks: { ...ticks, callback: (value) => `${currency} ${Number(value).toLocaleString()}` } },
                },
            });

            chart('chart-by-seller', 'doughnut', {
                labels: charts.bySeller.labels,
                datasets: [{
                    data: charts.bySeller.values,
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: cssVar('--color-surface', '#ffffff'),
                }],
            }, {
                cutout: '62%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { color: muted, boxWidth: 10, boxHeight: 10, padding: 12, font: { size: 11 } },
                    },
                    tooltip: { ...tooltip, callbacks: { label: (ctx) => ` ${formatMoney(ctx.parsed, currency)}` } },
                },
            });

            chart('chart-top-products', 'bar', {
                labels: charts.topProducts.labels,
                datasets: [{
                    label: labels.quantity,
                    data: charts.topProducts.values,
                    backgroundColor: withAlpha(success, 0.7),
                    hoverBackgroundColor: success,
                    borderRadius: 5,
                    borderSkipped: false,
                }],
            }, {
                indexAxis: 'y',
                scales: {
                    x: { grid, ticks: { ...ticks, precision: 0 } },
                    y: { grid: { display: false }, ticks: { ...ticks, callback: (value) => (String(value).length > 18 ? `${String(value).slice(0, 18)}…` : value) } },
                },
            });
        },
    };
}