<x-filament-panels::page>
    <div x-data="{ tab: 'appearance' }" class="space-y-6">

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
                    Colors & Fonts
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
                        SCSS
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

        {{-- ── Colors & Fonts tab ─────────────────────────────────────────── --}}
        <div x-show="tab === 'appearance'" class="space-y-4">
            @if ($this->isNonDefault)
                @php
                    $colorsInherited = $this->record->primary_color === null
                        && $this->record->header_bg_color === null
                        && $this->record->nav_link_color === null
                        && $this->record->nav_hover_color === null
                        && $this->record->nav_active_color === null
                        && $this->record->footer_bg_color === null
                        && $this->record->heading_font === null
                        && $this->record->body_font === null;
                @endphp
                <div x-data="{ inherit: @js($colorsInherited) }" class="space-y-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            x-model="inherit"
                            x-on:change="if (inherit) { $wire.clearAppearance() }"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        Inherit from Default
                    </label>

                    <div x-show="!inherit">
                        <x-filament-panels::form wire:submit="save">
                            {{ $this->form }}
                            <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
                        </x-filament-panels::form>
                    </div>

                    <div x-show="inherit" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        Colors and fonts are inherited from the Default template.
                    </div>
                </div>
            @else
                <x-filament-panels::form wire:submit="save">
                    {{ $this->form }}
                    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" />
                </x-filament-panels::form>
            @endif
        </div>

        {{-- ── SCSS tab ───────────────────────────────────────────────────── --}}
        @if (auth()->user()?->can('edit_theme_scss'))
            <div x-show="tab === 'scss'" class="space-y-4">
                @if ($this->isNonDefault)
                    @php $scssInherited = $this->record->custom_scss === null; @endphp
                    <div x-data="{ inherit: @js($scssInherited) }" class="space-y-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                x-model="inherit"
                                x-on:change="if (inherit) { $wire.clearScss() }"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                            />
                            Inherit from Default
                        </label>

                        <div x-show="!inherit">
                            @include('filament.resources.template-resource.pages.partials.scss-editor')
                        </div>

                        <div x-show="inherit" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            SCSS is inherited from the Default template.
                        </div>
                    </div>
                @else
                    @include('filament.resources.template-resource.pages.partials.scss-editor')
                @endif
            </div>
        @endif

        {{-- ── Header tab ─────────────────────────────────────────────────── --}}
        @if (auth()->user()?->can('edit_site_chrome'))
            <div x-show="tab === 'header'" class="space-y-4">
                @if ($this->isNonDefault)
                    @if ($this->hasCustomHeader)
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Custom header for this template. Widget changes are saved automatically.
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="inheritHeader"
                                    wire:confirm="Reset to the default header? Your custom header widgets will be orphaned."
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Inherit from Default
                                </button>
                                <button
                                    type="button"
                                    @click="document.activeElement?.blur()"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                                >
                                    Save
                                </button>
                            </div>
                        </div>

                        @livewire('page-builder', ['pageId' => $this->headerPageId], key('tmpl-header-' . $this->headerPageId))
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Header is inherited from the Default template.
                            </p>
                            <button
                                type="button"
                                wire:click="enableCustomHeader"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                            >
                                Use Custom Header
                            </button>
                        </div>
                    @endif
                @else
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
                        @livewire('page-builder', ['pageId' => $this->headerPageId], key('tmpl-header'))
                    @else
                        <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                            No header page found. Run the system page seeder to create the _header page.
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- ── Footer tab ─────────────────────────────────────────────────── --}}
        @if (auth()->user()?->can('edit_site_chrome'))
            <div x-show="tab === 'footer'" class="space-y-4">
                @if ($this->isNonDefault)
                    @if ($this->hasCustomFooter)
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Custom footer for this template. Widget changes are saved automatically.
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="inheritFooter"
                                    wire:confirm="Reset to the default footer? Your custom footer widgets will be orphaned."
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Inherit from Default
                                </button>
                                <button
                                    type="button"
                                    @click="document.activeElement?.blur()"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                                >
                                    Save
                                </button>
                            </div>
                        </div>

                        @livewire('page-builder', ['pageId' => $this->footerPageId], key('tmpl-footer-' . $this->footerPageId))
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Footer is inherited from the Default template.
                            </p>
                            <button
                                type="button"
                                wire:click="enableCustomFooter"
                                class="mt-3 inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                            >
                                Use Custom Footer
                            </button>
                        </div>
                    @endif
                @else
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
                        @livewire('page-builder', ['pageId' => $this->footerPageId], key('tmpl-footer'))
                    @else
                        <div class="rounded-lg border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                            No footer page found. Run the system page seeder to create the _footer page.
                        </div>
                    @endif
                @endif
            </div>
        @endif

    </div>
</x-filament-panels::page>
