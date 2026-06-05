<?php

namespace App\Services;

use App\Models\PageLayout;
use App\Models\PageWidget;

class AppearanceStyleComposer
{
    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private const ALIGNMENT_MAP = [
        'top-left'      => '0% 0%',
        'top-center'    => '50% 0%',
        'top-right'     => '100% 0%',
        'middle-left'   => '0% 50%',
        'center'        => '50% 50%',
        'middle-right'  => '100% 50%',
        'bottom-left'   => '0% 100%',
        'bottom-center' => '50% 100%',
        'bottom-right'  => '100% 100%',
    ];

    private const ALLOWED_FIT = ['cover', 'contain'];

    /**
     * Compose inline style and layout flags from a widget's appearance_config.
     *
     * @return array{inline_style: string, background_full_width: bool, content_full_width: bool}
     */
    public function compose(PageWidget $pw): array
    {
        $ac = $pw->appearance_config ?? [];
        $styleProps = [];

        // Background color
        $bgColor = $ac['background']['color'] ?? null;
        if (! empty($bgColor) && preg_match(self::HEX_PATTERN, $bgColor)) {
            $styleProps[] = 'background-color:' . $bgColor;
        }

        // Background image layers (gradient + image)
        $this->composeBackgroundImage($pw, $ac, $styleProps);

        // Text color
        $textColor = $ac['text']['color'] ?? null;
        if (! empty($textColor) && preg_match(self::HEX_PATTERN, $textColor)) {
            $styleProps[] = 'color:' . $textColor;
        }

        // Link color — emitted as a custom property the .np-site a rule consumes
        // via var() indirection, so it inherits to every link inside the widget
        // wrapper without a specificity fight.
        $linkColor = $ac['text']['link_color'] ?? null;
        if (! empty($linkColor) && preg_match(self::HEX_PATTERN, $linkColor)) {
            $styleProps[] = '--np-link-color:' . $linkColor;
        }

        // Text drop shadow
        if (! empty($ac['text']['shadow'])) {
            $styleProps[] = 'text-shadow:0 1px 3px rgba(0,0,0,0.6)';
        }

        // Horizontal padding/margin stays a literal declaration; vertical
        // (top/bottom) is emitted as --np-* custom properties so the host-layer
        // rule can scale it down at narrow widths (see composeVerticalSpacingVars).
        // Concrete 0 means "no override; let SCSS/intrinsic default apply".
        $padding = $ac['layout']['padding'] ?? [];
        foreach (['right', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }

        $margin = $ac['layout']['margin'] ?? [];
        foreach (['right', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        foreach (self::composeVerticalSpacingVars($padding, $margin) as $prop) {
            $styleProps[] = $prop;
        }

        // Border — concrete value object, default all-sides-off (a no-op)
        foreach (self::composeBorderProps($ac['layout']['border'] ?? []) as $prop) {
            $styleProps[] = $prop;
        }

        $fw = $this->resolveFullWidthForWidget($pw);

        return [
            'inline_style'          => implode(';', $styleProps),
            'background_full_width' => $fw['background_full_width'],
            'content_full_width'    => $fw['content_full_width'],
        ];
    }

    /**
     * Emit border style declarations from a concrete border value object:
     *   { top,right,bottom,left: bool; width: int(px); color: '#hex'; radius: int(px) }
     *
     * Shared by every appearance render surface (composer + ChromeRenderer) so
     * the builder, the public page, and chrome agree. Only enabled sides emit a
     * border; radius emits independently when > 0; box-sizing:border-box is
     * added whenever a side is on so an enabled border doesn't grow the box.
     *
     * @return array<int, string>
     */
    public static function composeBorderProps(array $border): array
    {
        $props = [];

        $width = isset($border['width']) ? max(0, (int) $border['width']) : 0;
        $color = $border['color'] ?? '';
        $colorOk = is_string($color) && preg_match(self::HEX_PATTERN, $color);

        $anyOn = false;
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (empty($border[$side])) {
                continue;
            }
            $anyOn = true;
            $decl = 'border-' . $side . ':' . $width . 'px solid';
            if ($colorOk) {
                $decl .= ' ' . $color;
            }
            $props[] = $decl;
        }

        $radius = isset($border['radius']) ? max(0, (int) $border['radius']) : 0;
        if ($radius > 0) {
            $props[] = 'border-radius:' . $radius . 'px';
        }

        if ($anyOn) {
            $props[] = 'box-sizing:border-box';
        }

        return $props;
    }

    /**
     * Canonical resolution chain for a widget's two full-width knobs:
     *   per-instance override → per-type default → false-false fallback.
     *
     * Column-child widgets are clamped to (false, false).
     * (background:false, content:true) is normalized to (true, true) — this state
     * is UI-prevented but may appear in legacy/seed/test data.
     *
     * @return array{background_full_width: bool, content_full_width: bool}
     */
    public function resolveFullWidthForWidget(PageWidget $pw): array
    {
        $ac = $pw->appearance_config ?? [];

        $bgInstance      = $ac['layout']['background_full_width'] ?? null;
        $contentInstance = $ac['layout']['content_full_width']    ?? null;

        $bgType      = $pw->widgetType?->background_full_width ?? false;
        $contentType = $pw->widgetType?->content_full_width    ?? false;

        $bg      = $bgInstance      !== null ? (bool) $bgInstance      : $bgType;
        $content = $contentInstance !== null ? (bool) $contentInstance : $contentType;

        if ($pw->layout_id !== null) {
            $bg = false;
            $content = false;
        }

        if (! $bg && $content) {
            $bg = true;
        }

        return [
            'background_full_width' => $bg,
            'content_full_width'    => $content,
        ];
    }

    /**
     * Canonical resolution chain for a column layout's two full-width knobs.
     * Same normalization rule as widgets.
     *
     * @return array{background_full_width: bool, content_full_width: bool}
     */
    public function resolveFullWidthForLayout(PageLayout $layout): array
    {
        $config = $layout->layout_config ?? [];

        // Parity with widget_types defaults (bg=true / content=false): a column
        // layout with no explicit full-width key defaults to a full-bleed
        // background, matching how widgets behave.
        $bg      = (bool) ($config['background_full_width'] ?? true);
        $content = (bool) ($config['content_full_width']    ?? false);

        if (! $bg && $content) {
            $bg = true;
        }

        return [
            'background_full_width' => $bg,
            'content_full_width'    => $content,
        ];
    }

    /**
     * Compose inline style for a page layout from its appearance_config.
     *
     * Layouts support background color, gradient, padding, and margin.
     * Layouts do not support background image, text color, text shadow, or
     * a full_width override on appearance_config (full_width lives on
     * layout_config for layouts).
     */
    public function composeForLayout(PageLayout $layout): string
    {
        $ac = $layout->appearance_config ?? [];
        $styleProps = [];

        $bgColor = $ac['background']['color'] ?? null;
        if (! empty($bgColor) && preg_match(self::HEX_PATTERN, $bgColor)) {
            $styleProps[] = 'background-color:' . $bgColor;
        }

        $gradientCss = app(GradientComposer::class)->compose($ac['background']['gradient'] ?? null);
        if ($gradientCss !== '') {
            $styleProps[] = 'background-image:' . $gradientCss;

            $alignment = $ac['background']['alignment'] ?? 'center';
            $position = self::ALIGNMENT_MAP[$alignment] ?? '50% 50%';
            $styleProps[] = 'background-position:' . $position;

            $fit = $ac['background']['fit'] ?? 'cover';
            if (! in_array($fit, self::ALLOWED_FIT, true)) {
                $fit = 'cover';
            }
            $styleProps[] = 'background-size:' . $fit;
            $styleProps[] = 'background-repeat:no-repeat';
        }

        $padding = $ac['layout']['padding'] ?? [];
        foreach (['right', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }

        $margin = $ac['layout']['margin'] ?? [];
        foreach (['right', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        foreach (self::composeVerticalSpacingVars($padding, $margin) as $prop) {
            $styleProps[] = $prop;
        }

        return implode(';', $styleProps);
    }

    /**
     * Vertical section-spacing primitive (session 335).
     *
     * Emit top/bottom padding & margin as --np-* custom properties instead of
     * literal padding/margin declarations, so a single host-layer @media
     * rule can scale them down at tablet/mobile widths — inline literals always
     * beat a stylesheet, which is exactly why nothing could compress them before.
     * The host rule consumes each property with a 0px fallback (resources/scss/
     * _layout.scss), so a side that is concrete 0 (or unset) emits no property
     * and resolves to the wrapper's intrinsic spacing — preserving the existing
     * "concrete 0 = no override" semantic. Horizontal (left/right) is unchanged
     * and stays a literal declaration at each call site.
     *
     * @param  array<string, mixed>  $padding  side-keyed (top/right/bottom/left)
     * @param  array<string, mixed>  $margin   side-keyed (top/right/bottom/left)
     * @return array<int, string>
     */
    public static function composeVerticalSpacingVars(array $padding, array $margin): array
    {
        $props = [];
        $map = [
            ['--np-pad-top',    $padding, 'top'],
            ['--np-pad-bottom', $padding, 'bottom'],
            ['--np-mar-top',    $margin,  'top'],
            ['--np-mar-bottom', $margin,  'bottom'],
        ];

        foreach ($map as [$cssVar, $src, $side]) {
            $val = isset($src[$side]) && $src[$side] !== '' ? (int) $src[$side] : 0;
            if ($val !== 0) {
                $props[] = $cssVar . ':' . $val . 'px';
            }
        }

        return $props;
    }

    private function composeBackgroundImage(PageWidget $pw, array $ac, array &$styleProps): void
    {
        $gradientCss = app(GradientComposer::class)->compose($ac['background']['gradient'] ?? null);
        $useCurrentPageHeader = (bool) ($ac['background']['use_current_page_header'] ?? false);

        if ($useCurrentPageHeader) {
            $imageUrl = $this->resolveCurrentPageHeaderUrl($pw);
        } else {
            $media = $pw->getFirstMedia('appearance_background_image');
            if ($media !== null) {
                $imageUrl = $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl();
            } else {
                $directUrl = $ac['background']['image_url'] ?? null;
                $imageUrl = is_string($directUrl) && $directUrl !== '' ? $directUrl : null;
            }
        }

        if ($gradientCss === '' && $imageUrl === null) {
            return;
        }

        // Build background-image layers: gradient paints over image
        $layers = [];
        if ($gradientCss !== '') {
            $layers[] = $gradientCss;
        }
        if ($imageUrl !== null) {
            // Quote the URL so filenames with spaces or parentheses (e.g.
            // "photo (1).jpg") produce valid CSS rather than silently failing to
            // paint; escape backslash/quote for CSS string safety. The whole
            // inline style is html-escaped by the renderer, so the emitted quotes
            // round-trip through the attribute unchanged.
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $imageUrl);
            $layers[] = 'url("' . $escaped . '")';
        }
        $styleProps[] = 'background-image:' . implode(', ', $layers);

        // Alignment (position)
        $alignment = $ac['background']['alignment'] ?? 'center';
        $position = self::ALIGNMENT_MAP[$alignment] ?? '50% 50%';
        $styleProps[] = 'background-position:' . $position;

        // Fit (size)
        $fit = $ac['background']['fit'] ?? 'cover';
        if (! in_array($fit, self::ALLOWED_FIT, true)) {
            $fit = 'cover';
        }
        $styleProps[] = 'background-size:' . $fit;
        $styleProps[] = 'background-repeat:no-repeat';
    }

    private function resolveCurrentPageHeaderUrl(PageWidget $pw): ?string
    {
        $page = ($pw->owner instanceof \App\Models\Page) ? $pw->owner : null;
        if ($page === null) {
            return null;
        }

        if ($page->type === 'event') {
            $event = $page->event;
            if ($event !== null) {
                $media = $event->getFirstMedia('event_header');
                if ($media !== null) {
                    return $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl();
                }
            }
            return null;
        }

        $media = $page->getFirstMedia('post_header');
        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl();
    }
}
