<x-filament-widgets::widget>
    <x-filament::section>
        <div class="np-dashboard-slot-grid">
            @foreach ($widgets as $handle => $pw)
                @php
                    $rendered = \App\Services\WidgetRenderer::render($pw, [], [], 'dashboard_grid');
                @endphp
                <div class="np-dashboard-slot-grid__cell" data-widget-handle="{{ $handle }}">
                    @if ($rendered['styles'])
                        <style>{!! $rendered['styles'] !!}</style>
                    @endif
                    {!! $rendered['html'] !!}
                    @if ($rendered['scripts'])
                        <script>{!! $rendered['scripts'] !!}</script>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
