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

<footer class="site-footer">
    <div class="site-container site-footer__bar">
        @if ($navItems->isNotEmpty())
            <nav class="site-footer__nav">
                @foreach ($navItems as $item)
                    @php
                        $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                    @endphp
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>
                @endforeach
            </nav>
        @endif

        <div class="site-footer__meta">
            @if ($copyrightText)
                <span class="site-footer__copyright">{{ $copyrightText }}</span>
            @endif

            @if ($showThemeToggle)
                <span x-data class="theme-toggle">
                    <x-svg-icon name="moon" class="icon-sm" />
                    <input
                        type="checkbox"
                        role="switch"
                        :checked="$store.theme.current === 'light'"
                        @change="$store.theme.toggle()"
                        aria-label="Toggle light/dark mode"
                    >
                    <x-svg-icon name="sun" class="icon-sm" />
                </span>
            @endif
        </div>
    </div>
</footer>
