<div
    class="page-builder"
    x-data
    x-on:open-widget-picker.window="if ($event.detail?.pageId === @js($pageId)) { $wire.openAddModal($event.detail?.insertPosition ?? null, $event.detail?.layoutId ?? null, $event.detail?.columnIndex ?? null) }"
    x-on:open-save-template-modal.window="if ($event.detail?.pageId === @js($pageId)) { $wire.openSaveTemplateModal() }"
>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Theme typography — scoped to the preview canvas and Quill editor     --}}
    {{-- so the public site's fonts/sizes render inside the builder without   --}}
    {{-- leaking into the Filament admin chrome.                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @php
        $__pbTypography    = \App\Services\TypographyResolver::load();
        $__pbTypographyCss = \App\Services\TypographyCompiler::compileScoped(
            ['.page-builder .widget-preview-scope', '.page-builder .ql-editor'],
            $__pbTypography,
        );
        $__pbBucketVars = [];
        $__pbHeadingFamily = $__pbTypography['buckets']['heading_family'] ?? null;
        $__pbBodyFamily    = $__pbTypography['buckets']['body_family'] ?? null;
        if ($__pbHeadingFamily) {
            $__pbBucketVars[] = "--font-family-heading: {$__pbHeadingFamily}";
        }
        if ($__pbBodyFamily) {
            $__pbBucketVars[] = "--font-family-body: {$__pbBodyFamily}";
        }

        $__pbFontsUsed   = \App\Services\TypographyCompiler::googleFontsUsed($__pbTypography);
        $__pbFontsHref   = '';
        if ($__pbFontsUsed) {
            $__pbFontsHref = 'https://fonts.googleapis.com/css2?' . collect($__pbFontsUsed)
                ->map(fn ($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;600;700')
                ->implode('&') . '&display=swap';
        }
    @endphp

    @if ($__pbFontsHref)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $__pbFontsHref }}">
    @endif

    @if ($__pbBucketVars)
        <style>.page-builder .widget-preview-scope, .page-builder .ql-editor { {!! implode('; ', $__pbBucketVars) !!}; }</style>
    @endif

    @if ($__pbTypographyCss)
        <style>{!! $__pbTypographyCss !!}</style>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Vue editor app                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    <div data-page-builder-app data-bootstrap='@json($bootstrapData)' wire:ignore></div>
    @vite('resources/js/page-builder-vue/main.ts')

    {{-- ------------------------------------------------------------------ --}}
    {{-- Add Block Modal                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showAddModal)
        <x-widget-picker-modal
            :widget-types="$widgetTypes"
            title="Add Block"
            show-property="showAddModal"
            create-action="$wire.createBlock"
        />
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Save as Template Modal                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($showSaveTemplateModal)
    @teleport('body')
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:keydown.escape.window="$wire.set('showSaveTemplateModal', false)"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Save as Content Template</h3>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Template Name</label>
                        <input
                            type="text"
                            wire:model="saveTemplateName"
                            placeholder="e.g. Landing Page, About Us…"
                            autofocus
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        @error('saveTemplateName') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
                        <textarea
                            wire:model="saveTemplateDescription"
                            rows="3"
                            placeholder="Describe what this template is for…"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showSaveTemplateModal', false)"
                        class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >Cancel</button>
                    <button
                        type="button"
                        wire:click="saveAsTemplate"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >Save Template</button>
                </div>
            </div>
        </div>
    @endteleport
    @endif

</div>
