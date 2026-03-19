<x-filament-widgets::widget>
    <x-filament::section heading="Integration Status">
        @php $integrations = $this->getIntegrations(); @endphp
        @if ($integrations)
            <ul class="space-y-2">
                @foreach ($integrations as $name => $key)
                    <li class="flex items-center justify-between text-sm">
                        <span>{{ $name }}</span>
                        <span class="text-success-600 font-medium">Connected</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400 italic">No integrations configured yet.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
