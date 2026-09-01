import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

import './toast';
import themeToggle from './theme';
import languageSwitcher from './language';
import dashboardCharts from './dashboard';
import sidebar from './sidebar';
import saleForm from './sale';

window.Alpine = Alpine;
window.Chart = Chart;

document.addEventListener('alpine:init', () => {
    Alpine.data('themeToggle', themeToggle);
    Alpine.data('languageSwitcher', languageSwitcher);
    Alpine.data('dashboardCharts', dashboardCharts);
    Alpine.data('sidebar', sidebar);
    Alpine.data('saleForm', saleForm);
});

Alpine.start();