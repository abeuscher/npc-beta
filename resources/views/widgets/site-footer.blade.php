@php
    $navHandle       = $config['nav_handle'] ?? 'footer';
    $showThemeToggle = $config['show_theme_toggle'] ?? true;
    $copyrightText   = $config['copyright_text'] ?? '';
    $navMenu         = \App\Models\NavigationMenu::where('handle', $navHandle)->first();
    $navItems        = $navMenu
        ? $navMenu->items()
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with('page')
            ->get()
        : collect();
@endphp

<footer class="py-6">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between flex-wrap gap-4">
        @if ($navItems->isNotEmpty())
            <nav class="flex items-center gap-4">
                @foreach ($navItems as $item)
                    @php
                        $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                    @endphp
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" class="no-underline hover:opacity-75">{{ $item->label }}</a>
                @endforeach
            </nav>
        @endif

        <div class="flex items-center gap-4">
            @if ($copyrightText)
                <span class="text-sm opacity-75">{{ $copyrightText }}</span>
            @endif

            @if ($showThemeToggle)
                <span x-data class="inline-flex items-center gap-2">
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
                </span>
            @endif
        </div>
    </div>
</footer>
