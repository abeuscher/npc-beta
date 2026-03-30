<x-filament-panels::page>
    <div
        x-data="{ tab: 'appearance' }"
        class="space-y-6"
    >
        {{-- Tab bar --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-6">
                <button
                    type="button"
                    @click="tab = 'appearance'"
                    :class="tab === 'appearance'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                    class="border-b-2 px-1 pb-3 text-sm font-medium"
                >
                    Appearance
                </button>

                @if (auth()->user()?->can('edit_theme_scss'))
                    <button
                        type="button"
                        @click="tab = 'scss'"
                        :class="tab === 'scss'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium"
                    >
                        SCSS Editor
                    </button>
                @endif

                @if (auth()->user()?->can('edit_site_chrome'))
                    <button
                        type="button"
                        @click="tab = 'header'"
                        :class="tab === 'header'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium"
                    >
                        Header
                    </button>

                    <button
                        type="button"
                        @click="tab = 'footer'"
                        :class="tab === 'footer'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium"
                    >
                        Footer
                    </button>
                @endif
            </nav>
        </div>

        {{-- Appearance tab --}}
        <div x-show="tab === 'appearance'">
            <x-filament-panels::form wire:submit="save">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getFormActions()"
                />
            </x-filament-panels::form>
        </div>

        {{-- SCSS Editor tab --}}
        @if (auth()->user()?->can('edit_theme_scss'))
            <div x-show="tab === 'scss'" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Theme SCSS</label>
                    <textarea
                        wire:model="themeScss"
                        rows="30"
                        style="font-family: monospace; font-size: 0.85rem;"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    ></textarea>
                </div>

                <div>
                    <button
                        type="button"
                        wire:click="saveAndBuild"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="saveAndBuild">Save &amp; Build</span>
                        <span wire:loading wire:target="saveAndBuild">Building…</span>
                    </button>
                </div>

                @if ($buildOutput !== '')
                    <div class="space-y-2">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Last build output</h3>
                        <pre class="overflow-x-auto rounded-lg border p-4 text-xs font-mono {{ $buildSuccess ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200' }}">{{ $buildOutput }}</pre>
                    </div>
                @endif
            </div>
        @endif

        {{-- Header tab --}}
        @if (auth()->user()?->can('edit_site_chrome'))
            <div x-show="tab === 'header'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Widget changes are saved automatically as you edit.
                    </p>
                    <button
                        type="button"
                        @click="document.activeElement?.blur()"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Save
                    </button>
                </div>

                @if ($this->headerPageId)
                    @livewire('page-builder', ['pageId' => $this->headerPageId], key('chrome-header'))
                @endif
            </div>
        @endif

        {{-- Footer tab --}}
        @if (auth()->user()?->can('edit_site_chrome'))
            <div x-show="tab === 'footer'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Widget changes are saved automatically as you edit.
                    </p>
                    <button
                        type="button"
                        @click="document.activeElement?.blur()"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Save
                    </button>
                </div>

                @if ($this->footerPageId)
                    @livewire('page-builder', ['pageId' => $this->footerPageId], key('chrome-footer'))
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
