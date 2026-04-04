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

<header class="site-header" x-data="{ open: false }">
    <div class="site-container site-header__bar">
        <div>
            @if ($logoPath)
                <img src="{{ asset($logoPath) }}" alt="{{ config('site.name', config('app.name')) }}" class="site-header__logo">
            @endif
            @if ($headerContent)
                {!! $headerContent !!}
            @endif
        </div>

        <button
            class="site-nav__toggle"
            @click="open = !open"
            :aria-expanded="open"
            aria-label="Toggle navigation"
        >
            <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <svg x-show="open" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <nav role="group" class="site-nav" :class="open ? 'is-open' : ''">
            @foreach ($headerNavItems as $item)
                @php
                    $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                @endphp
                @if ($item->children->isNotEmpty())
                    <span class="site-nav__dropdown">
                        <a
                            href="{{ $href }}"
                            target="{{ $item->target ?? '_self' }}"
                            {{ $currentUrl === $href ? 'aria-current="page"' : '' }}
                            class="site-nav__link"
                        >
                            {{ $item->label }}
                            <span class="site-nav__caret"></span>
                        </a>
                        <ul class="site-nav__dropdown-menu">
                            @foreach ($item->children as $child)
                                @php
                                    $childHref = ($child->page_id && $child->page) ? url('/' . $child->page->slug) : ($child->url ?? '#');
                                @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}" {{ $currentUrl === $childHref ? 'aria-current="page"' : '' }} class="site-nav__dropdown-link">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                @else
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }} class="site-nav__link">{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
