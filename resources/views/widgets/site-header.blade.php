@php
    $navHandle     = $config['nav_handle'] ?? 'primary';
    $headerContent = $config['header_content'] ?? '';
    $navMenu       = \App\Models\NavigationMenu::where('handle', $navHandle)->first();
    $navItems      = $navMenu
        ? $navMenu->items()
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
    $logoMedia  = $configMedia['logo'] ?? null;
@endphp

<header x-data="{ open: false }" class="relative">
    <div class="max-w-7xl mx-auto px-4 py-3 grid grid-cols-[1fr_auto] md:grid-cols-[1fr_2fr] items-center">
        <div>
            @if ($logoMedia)
                <x-picture :media="$logoMedia" alt="{{ config('site.name', config('app.name')) }}" class="h-10" />
            @endif
            @if ($headerContent)
                {!! $headerContent !!}
            @endif
        </div>

        <button
            class="md:hidden flex items-center justify-self-end p-1 bg-transparent border-0 cursor-pointer text-current leading-none"
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

        <nav
            role="group"
            class="hidden md:flex items-center justify-end gap-1
                   max-md:absolute max-md:top-full max-md:left-0 max-md:right-0 max-md:z-[200]
                   max-md:flex-col max-md:items-start max-md:gap-0
                   max-md:bg-white max-md:dark:bg-gray-800
                   max-md:border-b max-md:border-gray-200 max-md:dark:border-gray-700
                   max-md:shadow-lg
                   max-md:max-h-0 max-md:overflow-hidden max-md:opacity-0
                   max-md:transition-all max-md:duration-200"
            :class="open ? 'max-md:!flex max-md:!max-h-96 max-md:!opacity-100 max-md:!py-2' : ''"
        >
            @foreach ($navItems as $item)
                @php
                    $href = ($item->page_id && $item->page) ? url('/' . $item->page->slug) : ($item->url ?? '#');
                @endphp
                @if ($item->children->isNotEmpty())
                    <span class="relative inline-flex items-center group max-md:w-full max-md:flex-col max-md:items-start">
                        <a
                            href="{{ $href }}"
                            target="{{ $item->target ?? '_self' }}"
                            {{ $currentUrl === $href ? 'aria-current="page"' : '' }}
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded no-underline whitespace-nowrap hover:bg-gray-100 dark:hover:bg-gray-700 max-md:w-full max-md:px-4 max-md:py-2"
                        >
                            {{ $item->label }}
                            <span class="inline-block w-0 h-0 border-l-[0.2em] border-l-transparent border-r-[0.2em] border-r-transparent border-t-[0.25em] border-t-current transition-transform duration-150 group-hover:rotate-180"></span>
                        </a>
                        <ul class="hidden group-hover:block absolute top-full right-0 min-w-[12rem] m-0 py-2 list-none bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-lg z-[100] max-md:static max-md:shadow-none max-md:border-0 max-md:pl-4 max-md:min-w-0 max-md:w-full">
                            @foreach ($item->children as $child)
                                @php
                                    $childHref = ($child->page_id && $child->page) ? url('/' . $child->page->slug) : ($child->url ?? '#');
                                @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}" {{ $currentUrl === $childHref ? 'aria-current="page"' : '' }} class="block px-4 py-1 no-underline hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                @else
                    <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" {{ $currentUrl === $href ? 'aria-current="page"' : '' }} class="px-2.5 py-1.5 rounded no-underline whitespace-nowrap hover:bg-gray-100 dark:hover:bg-gray-700 max-md:w-full max-md:px-4 max-md:py-2">{{ $item->label }}</a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
