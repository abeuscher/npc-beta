@php
    $footerNavHandle = \App\Models\SiteSetting::get('footer_nav_handle', 'footer');
    $footerNavMenu   = \App\Models\NavigationMenu::where('handle', $footerNavHandle)->first();
    $footerNavItems  = $footerNavMenu
        ? $footerNavMenu->items()
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with('page')
            ->get()
        : collect();
@endphp

<footer class="py-6">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between flex-wrap gap-4">
        @if ($footerNavItems->isNotEmpty())
            <nav class="flex items-center gap-4">
                @foreach ($footerNavItems as $item)
                    @php
                        $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                    @endphp
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" class="no-underline hover:opacity-75">{{ $item->label }}</a>
                @endforeach
            </nav>
        @endif

        <div x-data class="inline-flex items-center gap-2">
            <x-svg-icon name="moon" class="w-4 h-4 shrink-0" />
            <input
                type="checkbox"
                role="switch"
                :checked="$store.theme.current === 'light'"
                @change="$store.theme.toggle()"
                aria-label="Toggle light/dark mode"
                class="m-0"
            >
            <x-svg-icon name="sun" class="w-4 h-4 shrink-0" />
        </div>
    </div>
</footer>
