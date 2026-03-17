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
    @if ($footerNavItems->isNotEmpty())
        <ul>
            @foreach ($footerNavItems as $item)
                @php
                    $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                @endphp
                <li>
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</footer>
