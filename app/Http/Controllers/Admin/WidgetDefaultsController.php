<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageWidget;
use App\Services\WidgetConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetDefaultsController extends Controller
{
    public function export(Request $request, WidgetConfigResolver $resolver): JsonResponse
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

        return response()->json([
            'php' => $this->buildMethod($ordered),
        ]);
    }

    private function buildMethod(array $map): string
    {
        $body = $this->phpArray($map, 2);

        return <<<PHP
public function defaults(): array
{
    return {$body};
}
PHP;
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
