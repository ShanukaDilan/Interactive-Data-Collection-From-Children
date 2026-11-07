import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

/** Learning Fun! palette (matches resources/css/app.css). */
export const PALETTE = {
    coral: '#FF6B6B',
    sky: '#4ECDC4',
    sun: '#FFD93D',
    mint: '#6BCB9B',
    lav: '#A68BFF',
    peach: '#FFB347',
    ink: '#2D3748',
    muted: '#718096',
    line: '#E8ECF0',
};

Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--font-nunito') || 'Nunito, system-ui, sans-serif';
Chart.defaults.color = PALETTE.muted;
Chart.defaults.borderColor = PALETTE.line;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.maintainAspectRatio = false;

const percentTicks = { callback: (value) => `${value}%` };

/**
 * Turn the compact config built in PHP (x-admin.chart) into a Chart.js config.
 * `format` = 'percent' | 'seconds' adds axis/tooltip units.
 */
function buildConfig({ type, labels, datasets, format, max, horizontal }) {
    const unit = format === 'percent' ? '%' : format === 'seconds' ? ' s' : '';
    const valueAxis = {
        beginAtZero: true,
        ...(max !== undefined ? { max } : {}),
        ticks: format === 'percent' ? percentTicks : { callback: (v) => `${v}${unit}` },
        grid: { color: PALETTE.line },
    };

    const isRadar = type === 'radar';

    return {
        type,
        data: {
            labels,
            datasets: datasets.map((dataset) => ({
                borderWidth: 2,
                borderRadius: type === 'bar' ? 8 : 0,
                pointRadius: type === 'line' ? 4 : 3,
                tension: 0.3,
                fill: isRadar,
                ...dataset,
                backgroundColor: dataset.backgroundColor ?? (isRadar ? `${dataset.color}33` : dataset.color),
                borderColor: dataset.borderColor ?? dataset.color,
                pointBackgroundColor: dataset.color,
            })),
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            plugins: {
                legend: { display: datasets.length > 1, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const value = ctx.parsed.r ?? (horizontal ? ctx.parsed.x : ctx.parsed.y);
                            return `${ctx.dataset.label ?? ''}: ${value === null ? '—' : value}${unit}`;
                        },
                    },
                },
            },
            scales: isRadar
                ? { r: { beginAtZero: true, max: max ?? 100, ticks: { ...percentTicks, backdropColor: 'transparent', stepSize: 25 }, grid: { color: PALETTE.line } } }
                : horizontal
                  ? { x: valueAxis, y: { grid: { display: false } } }
                  : { y: valueAxis, x: { grid: { display: false } } },
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        instance: null,
        init() {
            this.instance = new Chart(this.$refs.canvas, buildConfig(config));
        },
        destroy() {
            this.instance?.destroy();
        },
    }));
});
