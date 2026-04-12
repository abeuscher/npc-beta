<?php

namespace App\Services;

class GradientComposer
{
    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private const ALLOWED_TYPES = ['linear', 'radial'];

    private const CSS_OVERRIDE_PATTERN = '/^(?:linear|radial)-gradient\(\s*[#0-9a-fA-F,\s%.deg-]+\)$/';

    public function blank(): string
    {
        return '';
    }

    /**
     * Compose a CSS background-image string from a structured gradient value.
     *
     * Expected shape:
     *   ['gradients' => [
     *     ['type' => 'linear'|'radial', 'from' => '#hex', 'to' => '#hex', 'angle' => 180, 'css_override' => ''],
     *     ...
     *   ]]
     *
     * Returns an empty string when the value is missing, malformed, or contains
     * no valid gradient layers. Multi-gradient stacks are emitted with the second
     * gradient first (so it paints on top of the first, matching the editor preview).
     */
    public function compose(?array $value): string
    {
        if ($value === null) {
            return $this->blank();
        }

        $gradients = $value['gradients'] ?? null;
        if (! is_array($gradients) || count($gradients) === 0) {
            return $this->blank();
        }

        $layers = [];
        foreach ($gradients as $gradient) {
            if (! is_array($gradient)) {
                continue;
            }

            $layer = $this->composeOne($gradient);
            if ($layer !== '') {
                $layers[] = $layer;
            }
        }

        if (count($layers) === 0) {
            return $this->blank();
        }

        // Stack with later gradients painting on top: reverse order so the
        // last entry in the input array becomes the first layer in the CSS.
        return implode(', ', array_reverse($layers));
    }

    private function composeOne(array $gradient): string
    {
        $override = $gradient['css_override'] ?? '';
        if (is_string($override) && $override !== '') {
            return $this->sanitizeOverride($override);
        }

        $type = $gradient['type'] ?? 'linear';
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return '';
        }

        $from = $this->sanitizeHex($gradient['from'] ?? null);
        $to = $this->sanitizeHex($gradient['to'] ?? null);
        if ($from === null || $to === null) {
            return '';
        }

        $fromAlpha = $this->clampAlpha($gradient['from_alpha'] ?? 100);
        $toAlpha = $this->clampAlpha($gradient['to_alpha'] ?? 100);

        $fromColor = $fromAlpha < 100 ? $this->hexToRgba($from, $fromAlpha) : $from;
        $toColor = $toAlpha < 100 ? $this->hexToRgba($to, $toAlpha) : $to;

        if ($type === 'radial') {
            return "radial-gradient({$fromColor}, {$toColor})";
        }

        $angle = $this->sanitizeAngle($gradient['angle'] ?? 180);
        return "linear-gradient({$angle}deg, {$fromColor}, {$toColor})";
    }

    private function sanitizeHex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match(self::HEX_PATTERN, $trimmed) !== 1) {
            return null;
        }

        return strtolower($trimmed);
    }

    private function sanitizeAngle(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 180;
        }

        $int = (int) $value;
        if ($int < 0 || $int > 360) {
            return 180;
        }

        return $int;
    }

    private function clampAlpha(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 100;
        }

        return max(0, min(100, (int) $value));
    }

    private function hexToRgba(string $hex, int $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $r = hexdec($hex[0] . $hex[0]);
            $g = hexdec($hex[1] . $hex[1]);
            $b = hexdec($hex[2] . $hex[2]);
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        $a = round($alpha / 100, 2);

        return "rgba({$r}, {$g}, {$b}, {$a})";
    }

    private function sanitizeOverride(string $override): string
    {
        $trimmed = trim($override);

        // Stricter pattern: must look like a recognised CSS gradient function,
        // contain only hex colours and basic gradient grammar (digits, commas,
        // spaces, percent signs, decimal points, "deg", and minus signs).
        if (preg_match(self::CSS_OVERRIDE_PATTERN, $trimmed) !== 1) {
            return '';
        }

        return $trimmed;
    }
}
