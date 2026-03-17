<x-filament-widgets::widget>
    <x-filament::section>
        {!! \App\Models\SiteSetting::get('dashboard_welcome', '') !!}
    </x-filament::section>
</x-filament-widgets::widget>
