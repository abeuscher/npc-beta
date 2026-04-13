<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageWidget;
use App\Models\WidgetPreset;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PresetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'widget_type_id' => 'required|uuid|exists:widget_types,id',
            'widget_id'      => 'required|uuid|exists:page_widgets,id',
        ]);

        $widgetType = WidgetType::findOrFail($validated['widget_type_id']);
        $widget = PageWidget::findOrFail($validated['widget_id']);

        if ($widget->widget_type_id !== $widgetType->id) {
            throw ValidationException::withMessages([
                'widget_id' => 'Widget does not belong to the given widget type.',
            ]);
        }

        $appearanceKeys = $this->appearanceKeys($widgetType);

        // Presets are fat: materialize a complete appearance-group slice by overlaying
        // the instance's sparse config onto its resolved defaults. This guarantees apply
        // produces the saved look regardless of what the target instance already has set.
        $resolvedDefaults = app(WidgetConfigResolver::class)->resolvedDefaults($widget);
        $instanceConfig   = $widget->config ?? [];

        $filteredConfig = [];
        foreach ($appearanceKeys as $key) {
            if (array_key_exists($key, $instanceConfig)) {
                $filteredConfig[$key] = $instanceConfig[$key];
            } elseif (array_key_exists($key, $resolvedDefaults)) {
                $filteredConfig[$key] = $resolvedDefaults[$key];
            }
        }

        [$handle, $label] = $this->nextDraftIdentity($widgetType);

        $preset = WidgetPreset::create([
            'widget_type_id'    => $widgetType->id,
            'handle'            => $handle,
            'label'             => $label,
            'description'       => null,
            'config'            => $filteredConfig,
            'appearance_config' => $widget->appearance_config ?? [],
        ]);

        return response()->json(['preset' => $this->format($preset)], 201);
    }

    public function update(Request $request, WidgetPreset $preset): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $validated = $request->validate([
            'label'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'handle'      => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
        ]);

        if (array_key_exists('handle', $validated) && $validated['handle'] !== null) {
            $exists = WidgetPreset::where('widget_type_id', $preset->widget_type_id)
                ->where('handle', $validated['handle'])
                ->where('id', '!=', $preset->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'handle' => 'Handle must be unique for this widget type.',
                ]);
            }
        }

        $updates = [];
        foreach (['label', 'description', 'handle'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $updates[$field] = $validated[$field];
            }
        }

        if (! empty($updates)) {
            $preset->update($updates);
        }

        return response()->json(['preset' => $this->format($preset->fresh())]);
    }

    public function destroy(WidgetPreset $preset): JsonResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    private function appearanceKeys(WidgetType $widgetType): array
    {
        return collect($widgetType->config_schema ?? [])
            ->filter(fn ($field) => ($field['group'] ?? 'content') === 'appearance')
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    private function nextDraftIdentity(WidgetType $widgetType): array
    {
        $maxNumber = WidgetPreset::where('widget_type_id', $widgetType->id)
            ->where('handle', 'like', 'draft-%')
            ->get()
            ->map(fn ($p) => (int) substr($p->handle, 6))
            ->filter(fn ($n) => $n > 0)
            ->max();

        $n = ($maxNumber ?? 0) + 1;

        return ["draft-{$n}", "Draft {$n}"];
    }

    private function format(WidgetPreset $preset): array
    {
        return [
            'id'                => $preset->id,
            'widget_type_id'    => $preset->widget_type_id,
            'handle'            => $preset->handle,
            'label'             => $preset->label,
            'description'       => $preset->description,
            'config'            => $preset->config ?? [],
            'appearance_config' => $preset->appearance_config ?? [],
            'is_draft'          => true,
        ];
    }
}
