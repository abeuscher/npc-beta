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

<header x-data="{ open: false }">
    <div class="site-header-left">
        @if ($headerContent)
            {!! $headerContent !!}
        @endif
    </div>

    <div class="site-header-right">
        <button
            x-on:click="open = !open"
            x-bind:aria-expanded="open.toString()"
            aria-label="Toggle navigation"
            class="nav-toggle"
        >&#9776;</button>

        <ul x-show="open || true" x-cloak>
            @foreach ($headerNavItems as $item)
                @php
                    $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                @endphp
                <li>
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}">{{ $item->label }}</a>

                    @if ($item->children->isNotEmpty())
                        <ul>
                            @foreach ($item->children as $child)
                                @php
                                    $childHref = ($child->page_id && $child->page) ? url('/' . $child->page->slug) : ($child->url ?? '#');
                                @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</header>
