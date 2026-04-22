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
     * @return array{inline_style: string, is_full_width: bool}
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

        // Text drop shadow
        if (! empty($ac['text']['shadow'])) {
            $styleProps[] = 'text-shadow:0 1px 3px rgba(0,0,0,0.6)';
        }

        // Padding — concrete 0 means "no override; let SCSS/intrinsic default apply"
        $padding = $ac['layout']['padding'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }

        // Margin — concrete 0 means "no override; let SCSS/intrinsic default apply"
        $margin = $ac['layout']['margin'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        // Full width resolution
        $instanceFullWidth = $ac['layout']['full_width'] ?? null;
        $typeFullWidth = $pw->widgetType?->full_width ?? false;
        $isFullWidth = $instanceFullWidth !== null ? (bool) $instanceFullWidth : $typeFullWidth;

        // Column-child widgets cannot be full-width
        if ($pw->layout_id !== null) {
            $isFullWidth = false;
        }

        return [
            'inline_style'  => implode(';', $styleProps),
            'is_full_width' => $isFullWidth,
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
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }

        $margin = $ac['layout']['margin'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : 0;
            if ($val !== 0) {
                $styleProps[] = 'margin-' . $side . ':' . $val . 'px';
            }
        }

        return implode(';', $styleProps);
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
            $layers[] = 'url(' . $imageUrl . ')';
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
