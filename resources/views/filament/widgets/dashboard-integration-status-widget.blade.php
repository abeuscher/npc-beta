<x-filament-widgets::widget>
    <x-filament::section heading="Integration Status">
        @php
            $integrations = $this->getIntegrations();
            $buildServerStatus = $this->getBuildServerStatus();
        @endphp
        @if ($integrations || $buildServerStatus !== 'not_configured')
            <ul class="space-y-2">
                @foreach ($integrations as $name => $key)
                    <li class="flex items-center justify-between text-sm">
                        <span>{{ $name }}</span>
                        <span class="text-success-600 font-medium">Connected</span>
                    </li>
                @endforeach

                <li class="flex items-center justify-between text-sm">
                    <span>Build Server</span>
                    @if ($buildServerStatus === 'connected')
                        <span class="text-success-600 font-medium">Connected</span>
                    @elseif ($buildServerStatus === 'unreachable')
                        <span class="text-warning-600 font-medium">Unreachable</span>
                    @else
                        <span class="text-gray-400 italic">Not configured</span>
                    @endif
                </li>
            </ul>
        @else
            <p class="text-sm text-gray-400 italic">No integrations configured yet.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
