<x-filament-widgets::widget>
    <x-filament::section>
        @if ($hasWidgets)
            <div class="np-record-detail-view">
                @foreach ($widgets as $pw)
                    @php
                        $rendered = \App\Services\WidgetRenderer::render($pw, [], [], 'record_detail_sidebar', $record);
                    @endphp
                    <div class="np-record-detail-view__cell" data-widget-handle="{{ $pw->widgetType?->handle }}">
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
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
