<x-filament-panels::page>
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
</x-filament-panels::page>
