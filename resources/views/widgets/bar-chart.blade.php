@php
    $heading  = $config['heading'] ?? '';
    $xField   = $config['x_field'] ?? '';
    $yField   = $config['y_field'] ?? '';
    $xLabel   = $config['x_label'] ?? '';
    $yLabel   = $config['y_label'] ?? '';
    $barColor = $config['bar_fill_color'] ?? '';

    $items  = $collectionData['data'] ?? [];
    $labels = [];
    $values = [];
    foreach ($items as $item) {
        $labels[] = e((string) ($item[$xField] ?? ''));
        $values[] = (float) ($item[$yField] ?? 0);
    }

    $chartConfig = json_encode([
        'labels' => $labels,
        'values' => $values,
        'barColor' => $barColor,
        'xLabel' => $xLabel,
        'yLabel' => $yLabel,
    ]);
@endphp

@if (count($items) === 0)
    @php return; @endphp
@endif

<div class="widget-bar-chart" x-data x-init="
    let cfg = JSON.parse($refs.chartCfg.textContent);
    let canvas = $refs.canvas;
    if (canvas && window.Chart) {
        let barColor = cfg.barColor || getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#0172ad';
        let isPreview = !!canvas.closest('.widget-preview-scope');

        if (isPreview) {
            let containerWidth = parseFloat(getComputedStyle(canvas.parentElement).width);
            let aspectRatio = 2;
            canvas.width = containerWidth;
            canvas.height = containerWidth / aspectRatio;
            canvas.style.width = containerWidth + 'px';
            canvas.style.height = (containerWidth / aspectRatio) + 'px';
        }

        new window.Chart(canvas, {
            type: 'bar',
            data: { labels: cfg.labels, datasets: [{ data: cfg.values, backgroundColor: barColor, borderWidth: 0 }] },
            options: {
                responsive: !isPreview,
                maintainAspectRatio: true,
                events: isPreview ? [] : ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
                plugins: { legend: { display: false }, title: { display: false } },
                scales: {
                    x: { title: { display: !!cfg.xLabel, text: cfg.xLabel } },
                    y: { title: { display: !!cfg.yLabel, text: cfg.yLabel }, beginAtZero: true }
                }
            }
        });
    }
">
    <script x-ref="chartCfg" type="application/json">{!! $chartConfig !!}</script>

    @if ($heading)
        <h2>{{ $heading }}</h2>
    @endif

    <div style="position: relative; width: 100%;">
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
