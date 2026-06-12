<x-filament-panels::page.simple>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Two-factor authentication adds a second step to signing in. Scan the QR code below with an
            authenticator app (Google Authenticator, 1Password, Authy, …), then enter the 6-digit code it
            shows to finish setup.
        </p>

        <div class="flex flex-col items-center gap-4 rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="rounded-lg bg-white p-3">
                {!! $this->getQrCodeSvg() !!}
            </div>

            <div class="w-full text-center">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Can't scan? Enter this key manually
                </p>
                <code class="block break-all rounded bg-white px-3 py-2 font-mono text-sm text-gray-800 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-700">{{ $this->getSecret() }}</code>
            </div>
        </div>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-900/20">
            <p class="mb-2 text-sm font-semibold text-amber-900 dark:text-amber-200">
                Save your recovery codes
            </p>
            <p class="mb-3 text-xs text-amber-800 dark:text-amber-300/90">
                Each code can be used once if you lose access to your authenticator. Store them somewhere safe —
                they are shown only now.
            </p>
            <ul class="grid grid-cols-2 gap-2">
                @foreach ($this->getRecoveryCodes() as $recoveryCode)
                    <li class="rounded bg-white px-3 py-1.5 text-center font-mono text-sm text-gray-800 ring-1 ring-amber-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-amber-700/40">{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        </div>

        <x-filament-panels::form wire:submit="confirm">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    </div>
</x-filament-panels::page.simple>
