@php
    $heading  = $config['heading'] ?? '';
    $xField   = $config['x_field'] ?? '';
    $yField   = $config['y_field'] ?? '';
    $xLabel   = $config['x_label'] ?? '';
    $yLabel   = $config['y_label'] ?? '';
    $barColor = $config['bar_color'] ?? '';
    $chartId  = 'chart-' . \Illuminate\Support\Str::random(8);

    $items  = $collectionData['data'] ?? [];
    $labels = [];
    $values = [];
    foreach ($items as $item) {
        $labels[] = e((string) ($item[$xField] ?? ''));
        $values[] = (float) ($item[$yField] ?? 0);
    }
@endphp

@if (count($items) === 0)
    @php return; @endphp
@endif

<div class="widget-bar-chart">
    @if ($heading)
        <h2>{{ $heading }}</h2>
    @endif

    <div style="position: relative; width: 100%;">
        <canvas id="{{ $chartId }}"></canvas>
    </div>
</div>

<script>
(function () {
    var chartId = @json($chartId);
    var labels  = @json($labels);
    var values  = @json($values);
    var xLabel  = @json($xLabel);
    var yLabel  = @json($yLabel);
    var barColor = @json($barColor) || getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#0172ad';

    function init() {
        var ctx = document.getElementById(chartId);
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: barColor,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    x: {
                        title: { display: !!xLabel, text: xLabel }
                    },
                    y: {
                        title: { display: !!yLabel, text: yLabel },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
