@php
    $headerNavHandle = \App\Models\SiteSetting::get('header_nav_handle', 'primary');
    $headerContent   = \App\Models\SiteSetting::get('header_content', '');
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
@endphp

<header>
    <div class="container">
        <div>
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
                    <span x-data="{ open: false }" class="nav-dropdown"
                          @mouseenter="open = true"
                          @mouseleave="open = false">
                        <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a><button type="button" @click.stop="open = !open" :aria-expanded="open.toString()" aria-label="Toggle submenu">&#8964;</button>
                        <ul x-show="open" x-cloak>
                            @foreach ($item->children as $child)
                                @php
                                    $childHref = ($child->page_id && $child->page) ? url('/' . $child->page->slug) : ($child->url ?? '#');
                                @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                @else
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
