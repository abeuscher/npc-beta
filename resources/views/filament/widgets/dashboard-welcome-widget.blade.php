<x-filament-widgets::widget>
    <x-filament::section>
        @php $welcome = \App\Models\SiteSetting::get('dashboard_welcome', ''); @endphp
        @if ($welcome)
            {!! $welcome !!}
        @else
            <p class="text-sm text-gray-400 italic">Add a welcome message in General Settings.</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <x-filament::button
                type="button"
                color="primary"
                icon="heroicon-m-play-circle"
                data-np-tour-start="dashboard"
            >
                Tour the Product
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-play-circle"
                data-np-tour-goto="crm"
            >
                Tour the CRM
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-play-circle"
                data-np-tour-goto="cms"
            >
                Tour the CMS
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
