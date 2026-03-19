<x-filament-widgets::widget>
    <x-filament::section>
        @php $welcome = \App\Models\SiteSetting::get('dashboard_welcome', ''); @endphp
        @if ($welcome)
            {!! $welcome !!}
        @else
            <p class="text-sm text-gray-400 italic">Add a welcome message in General Settings.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
