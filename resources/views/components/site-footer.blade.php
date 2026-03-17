@php
    $footerNavHandle = \App\Models\SiteSetting::get('footer_nav_handle', 'footer');
    $footerNavItems  = \App\Models\NavigationItem::where('is_visible', true)
        ->where('menu_handle', $footerNavHandle)
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->with('page')
        ->get();
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
