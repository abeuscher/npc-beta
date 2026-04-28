<x-filament-widgets::widget>
    <x-filament::section>
        @if ($hasArrangement)
            <div class="np-dashboard-slot-grid">
                @foreach ($widgets as $pw)
                    @php
                        $rendered = \App\Services\WidgetRenderer::render($pw, [], [], 'dashboard_grid');
                    @endphp
                    <div class="np-dashboard-slot-grid__cell" data-widget-handle="{{ $pw->widgetType?->handle }}">
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
        @else
            <div class="np-dashboard-slot-grid__empty">
                No dashboard arrangement for your role — ask an admin to configure one in Tools → Dashboard View.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
