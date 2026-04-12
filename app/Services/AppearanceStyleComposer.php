<?php

namespace App\Services;

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

        // Padding
        $padding = $ac['layout']['padding'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($padding[$side]) && $padding[$side] !== '' ? (int) $padding[$side] : null;
            if ($val !== null) {
                $styleProps[] = 'padding-' . $side . ':' . $val . 'px';
            }
        }

        // Margin
        $margin = $ac['layout']['margin'] ?? [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $val = isset($margin[$side]) && $margin[$side] !== '' ? (int) $margin[$side] : null;
            if ($val !== null) {
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

    private function composeBackgroundImage(PageWidget $pw, array $ac, array &$styleProps): void
    {
        $gradientCss = app(GradientComposer::class)->compose($ac['background']['gradient'] ?? null);
        $media = $pw->getFirstMedia('appearance_background_image');
        $imageUrl = $media
            ? ($media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl())
            : null;

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
}
