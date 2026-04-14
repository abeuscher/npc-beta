<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageWidget;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetDefaultsController extends Controller
{
    public function export(Request $request, WidgetConfigResolver $resolver, WidgetRegistry $registry): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'widget_id' => 'required|uuid|exists:page_widgets,id',
        ]);

        $widget = PageWidget::with('widgetType')->findOrFail($validated['widget_id']);

        $resolved = array_merge(
            $resolver->resolvedDefaults($widget),
            $widget->config ?? [],
        );

        $schema = $widget->widgetType?->config_schema ?? [];

        $ordered = [];
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if ($key === null) continue;
            if (! array_key_exists($key, $resolved)) continue;
            $ordered[$key] = $resolved[$key];
        }

        $def = $registry->find($widget->widgetType?->handle ?? '');
        $appearanceDefaults = $def?->defaultAppearanceConfig() ?? [];
        $appearance = $this->mergeDeep($appearanceDefaults, $widget->appearance_config ?? []);

        return response()->json([
            'php' => $this->buildMethods($ordered, $appearance),
        ]);
    }

    private function buildMethods(array $map, array $appearance): string
    {
        $defaultsBody = $this->phpArray($map, 2);
        $appearanceBody = $this->phpArray($appearance, 2);

        return <<<PHP
public function defaults(): array
{
    return {$defaultsBody};
}

public function defaultAppearanceConfig(): array
{
    return {$appearanceBody};
}
PHP;
    }

    private function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($base[$key])) {
                $base[$key] = $this->mergeDeep($base[$key], $value);
                continue;
            }
            if (array_key_exists($key, $base) && is_int($base[$key]) && (is_string($value) || is_float($value)) && is_numeric($value)) {
                $base[$key] = (int) $value;
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    private function phpArray(array $v, int $indent): string
    {
        if ($v === []) return '[]';

        $isList = array_is_list($v);
        $pad = str_repeat('    ', $indent);
        $outer = str_repeat('    ', $indent - 1);

        if ($isList) {
            $parts = array_map(
                fn ($item) => $pad . $this->phpScalar($item, $indent + 1) . ',',
                $v
            );
            return "[\n" . implode("\n", $parts) . "\n{$outer}]";
        }

        $keyWidth = max(array_map(fn ($k) => strlen($this->phpString((string) $k)), array_keys($v)));
        $parts = [];
        foreach ($v as $k => $val) {
            $keyLit = str_pad($this->phpString((string) $k), $keyWidth, ' ');
            $parts[] = "{$pad}{$keyLit} => " . $this->phpScalar($val, $indent + 1) . ',';
        }
        return "[\n" . implode("\n", $parts) . "\n{$outer}]";
    }

    private function phpScalar(mixed $v, int $indent): string
    {
        if ($v === null) return 'null';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_int($v) || is_float($v)) return (string) $v;
        if (is_array($v)) return $this->phpArray($v, $indent);
        if (is_string($v)) return $this->phpString($v);
        return $this->phpString((string) $v);
    }

    private function phpString(string $s): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
    }
}
