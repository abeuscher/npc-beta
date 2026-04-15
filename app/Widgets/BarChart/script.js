window.NPWidgets = window.NPWidgets || {};

window.NPWidgets.barChart = function () {
    return {
        init() {
            const cfg = JSON.parse(this.$refs.chartCfg.textContent);
            const canvas = this.$refs.canvas;
            if (!canvas || !window.Chart) return;

            const barColor = cfg.barColor
                || getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim()
                || '#0172ad';

            const isPreview = !!canvas.closest('.widget-preview-scope');

            if (isPreview) {
                const containerWidth = parseFloat(getComputedStyle(canvas.parentElement).width);
                const aspectRatio = 2;
                canvas.width = containerWidth;
                canvas.height = containerWidth / aspectRatio;
                canvas.style.width = containerWidth + 'px';
                canvas.style.height = (containerWidth / aspectRatio) + 'px';
            }

            new window.Chart(canvas, {
                type: 'bar',
                data: {
                    labels: cfg.labels,
                    datasets: [{ data: cfg.values, backgroundColor: barColor, borderWidth: 0 }],
                },
                options: {
                    responsive: !isPreview,
                    maintainAspectRatio: true,
                    events: isPreview ? [] : ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
                    plugins: { legend: { display: false }, title: { display: false } },
                    scales: {
                        x: { title: { display: !!cfg.xLabel, text: cfg.xLabel } },
                        y: { title: { display: !!cfg.yLabel, text: cfg.yLabel }, beginAtZero: true },
                    },
                },
            });
        },
    };
};
