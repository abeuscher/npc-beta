@php
    $menuId          = $config['navigation_menu_id'] ?? null;
    $brandingType    = $config['branding_type'] ?? 'none';
    $brandingText    = $config['branding_text'] ?? '';
    $brandingMedia   = $configMedia['branding_image'] ?? null;
    $alignment       = $config['alignment'] ?? 'middle-left';
    $dropAnimation   = $config['drop_animation'] ?? 'fade';
    $dropAlign       = $config['drop_align'] ?? 'left';
    $dropBorderColor = $config['drop_border_color'] ?? '';
    $dropBorderWidth = (int) ($config['drop_border_width'] ?? 0);
    $dropFillColor   = $config['drop_fill_color'] ?? '#ffffff';
    $dropFillGradient = is_array($config['drop_fill_gradient'] ?? null) ? $config['drop_fill_gradient'] : null;
    $mobileAnimation = $config['mobile_animation'] ?? 'slide';
    $mobileBreakpoint = 768;
    $parentTemplate  = $config['parent_template'] ?? '<a href="{{url}}" class="widget-nav__link {{active_class}}">{{label}}</a>';
    $childTemplate   = $config['child_template'] ?? '<a href="{{url}}" class="widget-nav__drop-link {{active_class}}">{{label}}</a>';

    // Link colors
    $hexPattern    = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
    $linkColor     = $config['link_color'] ?? '';
    $hoverColor    = $config['hover_color'] ?? '';
    $dropLinkColor = $config['drop_link_color'] ?? '';
    $dropHoverColor = $config['drop_hover_color'] ?? '';

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

    // Helper to render a template with tokens
    $renderTemplate = function (string $template, string $label, string $url, string $activeClass) {
        return str_replace(
            ['{{label}}', '{{url}}', '{{active_class}}'],
            [e($label), e($url), e($activeClass)],
            $template
        );
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
    class="widget-nav widget-nav--drop-{{ $dropAnimation }} widget-nav--mobile-{{ $mobileAnimation }}"
    aria-label="{{ $navMenu->label ?? 'Navigation' }}"
    x-data="{
        mobileOpen: false,
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
        toggleMobile() { this.mobileOpen = !this.mobileOpen; },
    }"
    @keydown.escape.window="closeAll(); mobileOpen = false"
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

    {{-- Mobile hamburger --}}
    <button
        class="widget-nav__hamburger"
        @click="toggleMobile()"
        :aria-expanded="mobileOpen.toString()"
        aria-controls="{{ $widgetId }}-mobile"
        aria-label="Toggle navigation menu"
    >
        <span class="widget-nav__hamburger-bar" :class="mobileOpen && 'is-active'"></span>
    </button>

    {{-- Desktop menu --}}
    <ul class="widget-nav__menu" role="menubar" id="{{ $widgetId }}-menu">
        @foreach ($navItems as $index => $item)
            @php
                $href = $resolveUrl($item);
                $active = $isActive($href);
                $activeClass = $active ? 'is-active' : '';
                $hasChildren = $item->children->isNotEmpty();
                $itemId = $widgetId . '-item-' . $index;
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
                    role="menuitem"
                    class="widget-nav__item-wrap"
                    @if ($hasChildren)
                        aria-haspopup="true"
                        :aria-expanded="activeDropdown === '{{ $itemId }}' ? 'true' : 'false'"
                        @focusin="openDropdown('{{ $itemId }}')"
                        @keydown.arrow-down.prevent="openDropdown('{{ $itemId }}'); $nextTick(() => $el.closest('.widget-nav__item').querySelector('[role=menu] [role=menuitem] a')?.focus())"
                    @endif
                    @if ($active) aria-current="page" @endif
                >
                    {!! $renderTemplate($parentTemplate, $item->label, $href, $activeClass) !!}
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
                                    role="menuitem"
                                    class="widget-nav__drop-item-wrap"
                                    @if ($childActive) aria-current="page" @endif
                                    @if ($childHasChildren)
                                        aria-haspopup="true"
                                        :aria-expanded="activeDropdown === '{{ $childItemId }}' ? 'true' : 'false'"
                                        @focusin="openDropdown('{{ $childItemId }}')"
                                    @endif
                                >
                                    {!! $renderTemplate($childTemplate, $child->label, $childHref, $childActiveClass) !!}
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
                                            @endphp
                                            <li role="none" class="widget-nav__subdrop-item">
                                                <span role="menuitem" class="widget-nav__subdrop-item-wrap" @if ($gcActive) aria-current="page" @endif>
                                                    {!! $renderTemplate($childTemplate, $grandchild->label, $gcHref, $gcActiveClass) !!}
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
        x-show="mobileOpen"
        x-cloak
        role="menu"
        aria-label="{{ $navMenu->label ?? 'Navigation' }}"
    >
        <ul class="widget-nav__mobile-list">
            @foreach ($navItems as $index => $item)
                @php
                    $href = $resolveUrl($item);
                    $active = $isActive($href);
                    $activeClass = $active ? 'is-active' : '';
                    $hasChildren = $item->children->isNotEmpty();
                    $mobileItemId = $widgetId . '-mob-' . $index;
                @endphp
                <li
                    class="widget-nav__mobile-item"
                    role="none"
                    @if ($hasChildren)
                        x-data="{ subOpen: false }"
                    @endif
                >
                    <span role="menuitem" class="widget-nav__mobile-item-wrap">
                        {!! $renderTemplate($parentTemplate, $item->label, $href, $activeClass) !!}
                        @if ($hasChildren)
                            <button
                                class="widget-nav__mobile-toggle"
                                @click="subOpen = !subOpen"
                                :aria-expanded="subOpen.toString()"
                                aria-label="Expand {{ e($item->label) }}"
                            >
                                <span class="widget-nav__mobile-chevron" :class="subOpen && 'is-open'"></span>
                            </button>
                        @endif
                    </span>

                    @if ($hasChildren)
                        <ul class="widget-nav__mobile-sub" role="menu" x-show="subOpen" x-collapse>
                            @foreach ($item->children as $childIndex => $child)
                                @php
                                    $childHref = $resolveUrl($child);
                                    $childActive = $isActive($childHref);
                                    $childActiveClass = $childActive ? 'is-active' : '';
                                    $childHasChildren = $child->children->isNotEmpty();
                                @endphp
                                <li
                                    class="widget-nav__mobile-item widget-nav__mobile-item--child"
                                    role="none"
                                    @if ($childHasChildren)
                                        x-data="{ subOpen2: false }"
                                    @endif
                                >
                                    <span role="menuitem" class="widget-nav__mobile-item-wrap">
                                        {!! $renderTemplate($childTemplate, $child->label, $childHref, $childActiveClass) !!}
                                        @if ($childHasChildren)
                                            <button
                                                class="widget-nav__mobile-toggle"
                                                @click="subOpen2 = !subOpen2"
                                                :aria-expanded="subOpen2.toString()"
                                                aria-label="Expand {{ e($child->label) }}"
                                            >
                                                <span class="widget-nav__mobile-chevron" :class="subOpen2 && 'is-open'"></span>
                                            </button>
                                        @endif
                                    </span>

                                    @if ($childHasChildren)
                                        <ul class="widget-nav__mobile-sub widget-nav__mobile-sub--l3" role="menu" x-show="subOpen2" x-collapse>
                                            @foreach ($child->children as $grandchild)
                                                @php
                                                    $gcHref = $resolveUrl($grandchild);
                                                    $gcActive = $isActive($gcHref);
                                                    $gcActiveClass = $gcActive ? 'is-active' : '';
                                                @endphp
                                                <li class="widget-nav__mobile-item widget-nav__mobile-item--grandchild" role="none">
                                                    <span role="menuitem">
                                                        {!! $renderTemplate($childTemplate, $grandchild->label, $gcHref, $gcActiveClass) !!}
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
    </div>
</nav>
@endif
