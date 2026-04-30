<x-filament-widgets::widget>
    @if ($hasArrangement)
        <div class="np-dashboard-slot-grid">
            @foreach ($widgets as $pw)
                @php
                    $rendered = \App\Services\WidgetRenderer::render($pw, [], [], 'dashboard_grid');
                @endphp
                <x-filament::section>
                    <div class="np-dashboard-slot-grid__cell" data-widget-handle="{{ $pw->widgetType?->handle }}">
                        @if ($rendered['styles'])
                            <style>{!! $rendered['styles'] !!}</style>
                        @endif
                        {!! $rendered['html'] !!}
                        @if ($rendered['scripts'])
                            <script>{!! $rendered['scripts'] !!}</script>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <div class="np-dashboard-slot-grid__empty">
                No dashboard arrangement for your role — ask an admin to configure one in Tools → Dashboard View.
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
