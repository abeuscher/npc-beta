<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\AppearanceStyleComposer;
use App\Services\WidgetRegistry;
use App\Services\WidgetRenderer;
use Illuminate\Support\Facades\Artisan;

class WidgetDemoController extends Controller
{
    private const SEEDED_COLLECTION_HANDLES = [
        'carousel'      => 'carousel-demo',
        'bar_chart'     => 'chart-demo',
        'logo_garden'   => 'logo-garden-demo',
        'board_members' => 'board-members-demo',
    ];

    public function show(string $handle)
    {
        [$def, $widgetType, $config, $appearanceConfig] = $this->resolveBaseline($handle);

        return $this->renderWidget($handle, $widgetType, $config, $appearanceConfig);
    }

    public function showPreset(string $handle, string $presetHandle)
    {
        [$def, $widgetType, $config, $appearanceConfig] = $this->resolveBaseline($handle);

        $preset = collect($def->presets())->firstWhere('handle', $presetHandle);

        if (! $preset) {
            abort(404);
        }

        $config = array_merge($config, $preset['config'] ?? []);
        $appearanceConfig = $preset['appearance_config'] ?? [];

        return $this->renderWidget($handle, $widgetType, $config, $appearanceConfig);
    }

    private function resolveBaseline(string $handle): array
    {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }

        $def = app(WidgetRegistry::class)->find($handle);

        if (! $def) {
            abort(404);
        }

        if ($seederClass = $def->demoSeeder()) {
            Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
        }

        $widgetType = WidgetType::where('handle', $handle)->first();

        if (! $widgetType) {
            abort(404);
        }

        $config = array_merge($def->defaults(), $def->demoConfig());

        if (isset(self::SEEDED_COLLECTION_HANDLES[$handle])) {
            $config['collection_handle'] = self::SEEDED_COLLECTION_HANDLES[$handle];
        }

        return [$def, $widgetType, $config, $def->demoAppearanceConfig()];
    }

    private function renderWidget(string $handle, WidgetType $widgetType, array $config, array $appearanceConfig)
    {
        $pw = new PageWidget([
            'widget_type_id'    => $widgetType->id,
            'config'            => $config,
            'query_config'      => [],
            'appearance_config' => $appearanceConfig,
            'is_active'         => true,
        ]);
        $pw->setRelation('widgetType', $widgetType);

        $rendered = WidgetRenderer::render($pw);
        $appearance = app(AppearanceStyleComposer::class)->compose($pw);

        return view('dev.widget-demo', [
            'handle'     => $handle,
            'rendered'   => $rendered,
            'appearance' => $appearance,
        ]);
    }
}
