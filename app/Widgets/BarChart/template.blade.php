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
        'labels'   => $labels,
        'values'   => $values,
        'barColor' => $barColor,
        'xLabel'   => $xLabel,
        'yLabel'   => $yLabel,
    ]);
@endphp

@if (count($items) === 0)
    @php return; @endphp
@endif

<div class="widget-bar-chart" x-data="NPWidgets.barChart()">
    <script x-ref="chartCfg" type="application/json">{!! $chartConfig !!}</script>

    @if ($heading)
        <h2>{{ $heading }}</h2>
    @endif

    <div style="position: relative; width: 100%;">
        <canvas x-ref="canvas"></canvas>
    </div>
</div>
