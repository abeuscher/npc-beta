@php
    $headerNavHandle = \App\Models\SiteSetting::get('header_nav_handle', 'primary');
    $headerContent   = \App\Models\SiteSetting::get('header_content', '');
    $logoPath        = \App\Models\SiteSetting::get('logo_path');
    $headerNavMenu   = \App\Models\NavigationMenu::where('handle', $headerNavHandle)->first();
    $headerNavItems  = $headerNavMenu
        ? $headerNavMenu->items()
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'page',
                'children' => fn ($q) => $q->where('is_visible', true)->orderBy('sort_order')->with('page'),
            ])
            ->get()
        : collect();
    $currentUrl = url()->current();
@endphp

<header>
    <div class="container">
        <div>
            @if ($logoPath)
                <img src="{{ asset($logoPath) }}" alt="{{ config('site.name', config('app.name')) }}" class="site-logo">
            @endif
            @if ($headerContent)
                {!! $headerContent !!}
            @endif
        </div>

        <nav role="group">
            @foreach ($headerNavItems as $item)
                @php
                    $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                @endphp
                @if ($item->children->isNotEmpty())
                    <span class="nav-dropdown">
                        <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }} class="has-dropdown">{{ $item->label }}</a>
                        <ul>
                            @foreach ($item->children as $child)
                                @php
                                    $childHref = ($child->page_id && $child->page) ? url('/' . $child->page->slug) : ($child->url ?? '#');
                                @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}" {{ $currentUrl === $childHref ? 'aria-current="page"' : '' }}>{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                @else
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }}>{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
