<x-filament-widgets::widget>
    <x-filament::section>
        @php $welcome = \App\Models\SiteSetting::get('dashboard_welcome', ''); @endphp
        @if ($welcome)
            {!! $welcome !!}
        @else
            <p class="text-sm text-gray-400 italic">Add a welcome message in General Settings.</p>
        @endif

        <div class="mt-4">
            <x-filament::button
                type="button"
                color="primary"
                icon="heroicon-m-play-circle"
                data-np-tour-start
            >
                Tour the Product
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
