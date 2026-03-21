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

<footer>
    <div class="container">
        @if ($footerNavItems->isNotEmpty())
            <nav>
                @foreach ($footerNavItems as $item)
                    @php
                        $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                    @endphp
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>
                @endforeach
            </nav>
        @endif

        <div x-data class="theme-toggle">
            <x-svg-icon name="moon" />
            <input
                type="checkbox"
                role="switch"
                :checked="$store.theme.current === 'light'"
                @change="$store.theme.toggle()"
                aria-label="Toggle light/dark mode"
            >
            <x-svg-icon name="sun" />
        </div>
    </div>
</footer>
