@php
    $menuId          = $config['navigation_menu_id'];
    $brandingType    = $config['branding_type'];
    $brandingText    = $config['branding_text'];
    $brandingMedia   = $configMedia['branding_image'] ?? null;
    $alignment       = $config['alignment'];
    $orientation     = $config['orientation'] ?? 'horizontal';
    $dropAnimation   = $config['drop_animation'];
    $dropAlign       = $config['drop_align'];
    $dropBorderColor = $config['drop_border_color'];
    $dropBorderWidth = (int) $config['drop_border_width'];
    $dropFillColor   = $config['drop_fill_color'];
    $dropFillGradient = is_array($config['drop_fill_gradient']) ? $config['drop_fill_gradient'] : null;
    $mobileAnimation = $config['mobile_animation'];
    $mobileBreakpoint = 768;
    $parentTemplate  = $config['parent_template'];
    $childTemplate   = $config['child_template'];

    // Link colors
    $hexPattern     = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
    $linkColor      = $config['link_color'];
    $hoverColor     = $config['hover_color'];
    $dropLinkColor  = $config['drop_link_color'];
    $dropHoverColor = $config['drop_hover_color'];

    $navColorVars = [];
    if ($linkColor && preg_match($hexPattern, $linkColor))         $navColorVars[] = '--nav-link-color:' . $linkColor;
    if ($hoverColor && preg_match($hexPattern, $hoverColor))       $navColorVars[] = '--nav-hover-color:' . $hoverColor;
    if ($dropLinkColor && preg_match($hexPattern, $dropLinkColor)) $navColorVars[] = '--nav-drop-link-color:' . $dropLinkColor;
    if ($dropHoverColor && preg_match($hexPattern, $dropHoverColor)) $navColorVars[] = '--nav-drop-hover-color:' . $dropHoverColor;

    // Resolve navigation menu
    $navMenu = $menuId
        ? \App\Models\NavigationMenu::find($menuId)
        : null;

    $navItems = $navMenu
        ? $navMenu->items()
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'page',
                'children' => fn ($q) => $q->where('is_visible', true)->orderBy('sort_order')->with([
                    'page',
                    'children' => fn ($q2) => $q2->where('is_visible', true)->orderBy('sort_order')->with('page'),
                ]),
            ])
            ->get()
        : collect();

    $currentPath = '/' . ltrim(request()->path(), '/');

    // Dropdown panel inline styles
    $dropStyles = [];
    if ($dropBorderWidth > 0 && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $dropBorderColor)) {
        $dropStyles[] = 'border:' . $dropBorderWidth . 'px solid ' . $dropBorderColor;
    }
    $gradientCss = app(\App\Services\GradientComposer::class)->compose($dropFillGradient);
    if ($gradientCss !== '') {
        $dropStyles[] = 'background-image:' . $gradientCss;
    } elseif (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $dropFillColor)) {
        $dropStyles[] = 'background-color:' . $dropFillColor;
    }
    $dropStyleAttr = implode(';', $dropStyles);

    // Alignment classes — handle 'center' (no hyphen) as 'center center'
    if ($alignment === 'center') {
        $alignVert = 'center';
        $alignHoriz = 'center';
    } else {
        $alignParts = explode('-', $alignment, 2);
        $alignVert  = $alignParts[0] ?? 'middle';
        $alignHoriz = $alignParts[1] ?? 'left';
    }
    $justifyMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
    $alignMap   = ['top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end'];
    $justifyContent = $justifyMap[$alignHoriz] ?? 'flex-start';
    $alignItems     = $alignMap[$alignVert] ?? 'center';

    // Helper to resolve an item's URL
    $resolveUrl = function ($item) {
        if ($item->page_id && $item->page) {
            return url('/' . $item->page->slug);
        }
        return $item->url ?? '#';
    };

    // Helper to render a nav-item template. Beyond token substitution it:
    //  (1) strips the wrapping <p> the Quill-backed template editor adds — nav
    //      items are links, not prose, so the <p> is invalid leakage; and
    //  (2) hoists role="menuitem" (plus any per-item ARIA passed in
    //      $anchorAttrs, e.g. aria-current) onto the anchor, the actual
    //      interactive element, rather than the layout wrapper <span>.
    $renderTemplate = function (string $template, string $label, string $url, string $activeClass, string $anchorAttrs = '') {
        $html = str_replace(
            ['{{label}}', '{{url}}', '{{active_class}}'],
            [e($label), e($url), e($activeClass)],
            $template
        );

        $html = preg_replace('#^\s*<p\b[^>]*>(.*)</p>\s*$#is', '$1', $html) ?? $html;

        $inject = trim('role="menuitem" ' . $anchorAttrs);
        return preg_replace('/<a\b/i', '<a ' . $inject, $html, 1) ?? $html;
    };

    // Helper to check if a URL matches the current path
    $isActive = function (string $href) use ($currentPath) {
        $itemPath = parse_url($href, PHP_URL_PATH) ?? '/';
        return rtrim($itemPath, '/') === rtrim($currentPath, '/');
    };

    $widgetId = 'nav-' . substr(md5(uniqid()), 0, 8);
@endphp

@if ($navItems->isEmpty())
    {{-- Empty state: render nothing --}}
@elseif ($orientation === 'columns')
    {{-- Columns / footer preset: each top-level item is a heading column with
         its children listed beneath. No dropdowns, no JS — every link visible. --}}
    <nav
        class="widget-nav widget-nav--columns"
        aria-label="{{ $navMenu->label ?? 'Navigation' }}"
        style="--nav-justify: {{ $justifyContent }}{{ !empty($navColorVars) ? '; ' . implode('; ', $navColorVars) : '' }}"
    >
        <ul class="widget-nav__columns">
            @foreach ($navItems as $item)
                @php
                    $href = $resolveUrl($item);
                    $headingIsLink = $item->page_id || ($item->url && $item->url !== '#');
                @endphp
                <li class="widget-nav__column">
                    @if ($headingIsLink)
                        <a href="{{ $href }}" target="{{ $item->target ?? '_self' }}" class="widget-nav__column-heading">{{ $item->label }}</a>
                    @else
                        <span class="widget-nav__column-heading">{{ $item->label }}</span>
                    @endif

                    @if ($item->children->isNotEmpty())
                        <ul class="widget-nav__column-links">
                            @foreach ($item->children as $child)
                                @php $childHref = $resolveUrl($child); @endphp
                                <li>
                                    <a href="{{ $childHref }}" target="{{ $child->target ?? '_self' }}" class="widget-nav__column-link {{ ($childHref !== '#' && $isActive($childHref)) ? 'is-active' : '' }}">{{ $child->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@else
<style>
    @media (min-width: {{ $mobileBreakpoint + 1 }}px) {
        #{{ $widgetId }} .widget-nav__hamburger { display: none; }
        #{{ $widgetId }} .widget-nav__mobile { display: none !important; }
    }
    @media (max-width: {{ $mobileBreakpoint }}px) {
        #{{ $widgetId }} .widget-nav__menu { display: none; }
        #{{ $widgetId }} .widget-nav__hamburger { display: block; }
    }
</style>
<nav
    id="{{ $widgetId }}"
    class="widget-nav widget-nav--{{ $orientation }} widget-nav--drop-{{ $dropAnimation }} widget-nav--mobile-{{ $mobileAnimation }}"
    aria-label="{{ $navMenu->label ?? 'Navigation' }}"
    x-data="{
        activeDropdown: null,
        hoverTimeout: null,
        openDropdown(id) {
            clearTimeout(this.hoverTimeout);
            this.activeDropdown = id;
        },
        closeDropdown(id) {
            this.hoverTimeout = setTimeout(() => {
                if (this.activeDropdown === id) this.activeDropdown = null;
            }, 150);
        },
        closeAll() { this.activeDropdown = null; },
    }"
    @keydown.escape.window="closeAll()"
    style="--nav-justify: {{ $justifyContent }}; --nav-align: {{ $alignItems }}{{ !empty($navColorVars) ? '; ' . implode('; ', $navColorVars) : '' }}"
>
    {{-- Branding slot --}}
    @if ($brandingType !== 'none')
        <div class="widget-nav__brand">
            @if ($brandingType === 'logo' && $brandingMedia)
                <a href="/" class="widget-nav__brand-link">
                    <x-picture :media="$brandingMedia" alt="{{ $brandingText }}" class="widget-nav__brand-logo" />
                </a>
            @elseif ($brandingType === 'icon' && $brandingMedia)
                <a href="/" class="widget-nav__brand-link">
                    <x-picture :media="$brandingMedia" alt="{{ $brandingText }}" class="widget-nav__brand-icon" />
                </a>
            @elseif ($brandingType === 'text' && $brandingText !== '')
                <a href="/" class="widget-nav__brand-link widget-nav__brand-text">{{ $brandingText }}</a>
            @endif
        </div>
    @endif

    {{-- Mobile toggle — CSS-only: a visually-hidden checkbox holds the
         open/closed state, the hamburger + close-X are <label>s for it, and the
         menu reveal + body scroll-lock are driven by :checked in the stylesheet.
         No JS. --}}
    <input
        type="checkbox"
        id="{{ $widgetId }}-toggle"
        class="widget-nav__toggle"
        aria-label="Toggle navigation menu"
        aria-controls="{{ $widgetId }}-mobile"
    >
    <label for="{{ $widgetId }}-toggle" class="widget-nav__hamburger">
        <span class="widget-nav__hamburger-bar" aria-hidden="true"></span>
    </label>

    {{-- Desktop menu --}}
    <ul class="widget-nav__menu" role="menubar" id="{{ $widgetId }}-menu">
        @foreach ($navItems as $index => $item)
            @php
                $href = $resolveUrl($item);
                $active = $isActive($href);
                $activeClass = $active ? 'is-active' : '';
                $hasChildren = $item->children->isNotEmpty();
                $itemId = $widgetId . '-item-' . $index;
                $anchorAttrs = $active ? 'aria-current="page"' : '';
            @endphp
            <li
                role="none"
                class="widget-nav__item{{ $hasChildren ? ' widget-nav__item--has-drop' : '' }}"
                @if ($hasChildren)
                    @mouseenter="openDropdown('{{ $itemId }}')"
                    @mouseleave="closeDropdown('{{ $itemId }}')"
                @endif
            >
                <span
                    class="widget-nav__item-wrap"
                    @if ($hasChildren)
                        aria-haspopup="true"
                        :aria-expanded="activeDropdown === '{{ $itemId }}' ? 'true' : 'false'"
                        @focusin="openDropdown('{{ $itemId }}')"
                        @keydown.arrow-down.prevent="openDropdown('{{ $itemId }}'); $nextTick(() => $el.closest('.widget-nav__item').querySelector('[role=menu] [role=menuitem]')?.focus())"
                    @endif
                >
                    {!! $renderTemplate($parentTemplate, $item->label, $href, $activeClass, $anchorAttrs) !!}
                    @if ($hasChildren)
                        <span class="widget-nav__caret" aria-hidden="true"></span>
                    @endif
                </span>

                @if ($hasChildren)
                    <ul
                        class="widget-nav__dropdown widget-nav__dropdown--{{ $dropAlign }}"
                        role="menu"
                        aria-label="{{ $item->label }}"
                        x-show="activeDropdown === '{{ $itemId }}'"
                        x-cloak
                        @if ($dropStyleAttr) style="{{ $dropStyleAttr }}" @endif
                        @keydown.escape.prevent="closeAll(); $el.closest('.widget-nav__item').querySelector('[role=menuitem]')?.focus()"
                    >
                        @foreach ($item->children as $childIndex => $child)
                            @php
                                $childHref = $resolveUrl($child);
                                $childActive = $isActive($childHref);
                                $childActiveClass = $childActive ? 'is-active' : '';
                                $childHasChildren = $child->children->isNotEmpty();
                                $childItemId = $itemId . '-' . $childIndex;
                                $childAnchorAttrs = $childActive ? 'aria-current="page"' : '';
                            @endphp
                            <li
                                role="none"
                                class="widget-nav__drop-item{{ $childHasChildren ? ' widget-nav__drop-item--has-sub' : '' }}"
                                @if ($childHasChildren)
                                    @mouseenter="openDropdown('{{ $childItemId }}')"
                                    @mouseleave="closeDropdown('{{ $childItemId }}')"
                                @endif
                            >
                                <span
                                    class="widget-nav__drop-item-wrap"
                                    @if ($childHasChildren)
                                        aria-haspopup="true"
                                        :aria-expanded="activeDropdown === '{{ $childItemId }}' ? 'true' : 'false'"
                                        @focusin="openDropdown('{{ $childItemId }}')"
                                    @endif
                                >
                                    {!! $renderTemplate($childTemplate, $child->label, $childHref, $childActiveClass, $childAnchorAttrs) !!}
                                    @if ($childHasChildren)
                                        <span class="widget-nav__caret widget-nav__caret--sub" aria-hidden="true"></span>
                                    @endif
                                </span>

                                @if ($childHasChildren)
                                    <ul
                                        class="widget-nav__subdrop widget-nav__subdrop--{{ $dropAlign }}"
                                        role="menu"
                                        aria-label="{{ $child->label }}"
                                        x-show="activeDropdown === '{{ $childItemId }}'"
                                        x-cloak
                                        @if ($dropStyleAttr) style="{{ $dropStyleAttr }}" @endif
                                        @keydown.escape.prevent="closeAll()"
                                    >
                                        @foreach ($child->children as $grandchild)
                                            @php
                                                $gcHref = $resolveUrl($grandchild);
                                                $gcActive = $isActive($gcHref);
                                                $gcActiveClass = $gcActive ? 'is-active' : '';
                                                $gcAnchorAttrs = $gcActive ? 'aria-current="page"' : '';
                                            @endphp
                                            <li role="none" class="widget-nav__subdrop-item">
                                                <span class="widget-nav__subdrop-item-wrap">
                                                    {!! $renderTemplate($childTemplate, $grandchild->label, $gcHref, $gcActiveClass, $gcAnchorAttrs) !!}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>

    {{-- Mobile menu (duplicated list for independent scroll/animation) --}}
    <div
        class="widget-nav__mobile"
        id="{{ $widgetId }}-mobile"
        role="menu"
        aria-label="{{ $navMenu->label ?? 'Navigation' }}"
        @if (!empty($navColorVars)) style="{{ implode('; ', $navColorVars) }}" @endif
    >
        <ul class="widget-nav__mobile-list">
            @foreach ($navItems as $index => $item)
                @php
                    $href = $resolveUrl($item);
                    $active = $isActive($href);
                    $activeClass = $active ? 'is-active' : '';
                    $hasChildren = $item->children->isNotEmpty();
                    $mobileItemId = $widgetId . '-mob-' . $index;
                    $anchorAttrs = $active ? 'aria-current="page"' : '';
                @endphp
                <li class="widget-nav__mobile-item" role="none">
                    @if ($hasChildren)
                        <input type="checkbox" id="{{ $mobileItemId }}-sub" class="widget-nav__mobile-subtoggle" aria-label="Expand {{ e($item->label) }}">
                    @endif
                    <span class="widget-nav__mobile-item-wrap">
                        {!! $renderTemplate($parentTemplate, $item->label, $href, $activeClass, $anchorAttrs) !!}
                        @if ($hasChildren)
                            <label for="{{ $mobileItemId }}-sub" class="widget-nav__mobile-toggle" aria-hidden="true">
                                <span class="widget-nav__mobile-chevron"></span>
                            </label>
                        @endif
                    </span>

                    @if ($hasChildren)
                        <ul class="widget-nav__mobile-sub" role="menu">
                            @foreach ($item->children as $childIndex => $child)
                                @php
                                    $childHref = $resolveUrl($child);
                                    $childActive = $isActive($childHref);
                                    $childActiveClass = $childActive ? 'is-active' : '';
                                    $childHasChildren = $child->children->isNotEmpty();
                                    $mobileChildId = $mobileItemId . '-' . $childIndex;
                                    $childAnchorAttrs = $childActive ? 'aria-current="page"' : '';
                                @endphp
                                <li class="widget-nav__mobile-item widget-nav__mobile-item--child" role="none">
                                    @if ($childHasChildren)
                                        <input type="checkbox" id="{{ $mobileChildId }}-sub" class="widget-nav__mobile-subtoggle" aria-label="Expand {{ e($child->label) }}">
                                    @endif
                                    <span class="widget-nav__mobile-item-wrap">
                                        {!! $renderTemplate($childTemplate, $child->label, $childHref, $childActiveClass, $childAnchorAttrs) !!}
                                        @if ($childHasChildren)
                                            <label for="{{ $mobileChildId }}-sub" class="widget-nav__mobile-toggle" aria-hidden="true">
                                                <span class="widget-nav__mobile-chevron"></span>
                                            </label>
                                        @endif
                                    </span>

                                    @if ($childHasChildren)
                                        <ul class="widget-nav__mobile-sub widget-nav__mobile-sub--l3" role="menu">
                                            @foreach ($child->children as $grandchild)
                                                @php
                                                    $gcHref = $resolveUrl($grandchild);
                                                    $gcActive = $isActive($gcHref);
                                                    $gcActiveClass = $gcActive ? 'is-active' : '';
                                                    $gcAnchorAttrs = $gcActive ? 'aria-current="page"' : '';
                                                @endphp
                                                <li class="widget-nav__mobile-item widget-nav__mobile-item--grandchild" role="none">
                                                    {!! $renderTemplate($childTemplate, $grandchild->label, $gcHref, $gcActiveClass, $gcAnchorAttrs) !!}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</nav>
@endif
