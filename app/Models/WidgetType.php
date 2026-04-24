<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WidgetType extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    /**
     * Maps bare page slugs (stripped of any type prefix) to the widget handles
     * that are required on that page. Required widgets cannot be removed from the
     * page builder and their widget types cannot be deleted from the admin.
     *
     * Keys are the bare slug (without system_prefix or portal_prefix).
     * Values are arrays of widget handles that are required on that page.
     */
    protected static array $requiredFor = [
        'login'           => ['portal_login'],
        'signup'          => ['portal_signup'],
        'forgot-password' => ['portal_forgot_password'],
        'account'         => ['portal_account_dashboard'],
    ];

    public static function requiredForPage(string $bareSlug): array
    {
        return static::$requiredFor[$bareSlug] ?? [];
    }

    public static function isPinned(string $handle): bool
    {
        foreach (static::$requiredFor as $handles) {
            if (in_array($handle, $handles, true)) {
                return true;
            }
        }

        return false;
    }

    protected $fillable = [
        'handle',
        'label',
        'description',
        'category',
        'allowed_page_types',
        'render_mode',
        'collections',
        'assets',
        'default_open',
        'full_width',
        'config_schema',
        'template',
        'css',
        'js',
        'variable_name',
        'code',
        'required_config',
    ];

    protected $casts = [
        'collections'        => 'array',
        'assets'             => 'array',
        'config_schema'      => 'array',
        'category'           => 'array',
        'default_open'       => 'boolean',
        'full_width'         => 'boolean',
        'allowed_page_types' => 'array',
        'required_config'    => 'array',
    ];

    public function getDefaultConfig(): array
    {
        if ($def = app(\App\Services\WidgetRegistry::class)->find($this->handle)) {
            return $def->defaults();
        }

        $config = [];
        foreach ($this->config_schema ?? [] as $field) {
            if (empty($field['key'])) {
                continue;
            }
            $config[$field['key']] = $field['default'] ?? match ($field['type'] ?? 'text') {
                'toggle'     => false,
                'number'     => null,
                'image'      => null,
                'checkboxes' => [],
                default      => '',
            };
        }

        return $config;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')->singleFile();
        $this->addMediaCollection('thumbnail_hover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('picker')
            ->width(320)
            ->height(180)
            ->format('webp')
            ->nonQueued();
    }

    public function pageWidgets(): HasMany
    {
        return $this->hasMany(PageWidget::class);
    }

    public function draftPresets(): HasMany
    {
        return $this->hasMany(WidgetPreset::class);
    }

    /**
     * Return widget types formatted for the picker modal, filtered by page type.
     *
     * When `$slotHandle` is provided, results are also filtered to widgets whose
     * definition's `allowedSlots()` includes that slot — enforces slot-locality
     * at the picker level (e.g. dashboard mode shows only `dashboard_grid` widgets).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPicker(?string $pageType = 'default', ?string $slotHandle = null): array
    {
        $registry = app(\App\Services\WidgetRegistry::class);

        return static::orderBy('label')
            ->with(['media', 'draftPresets'])
            ->get()
            ->filter(fn ($wt) => $pageType === null || $wt->allowed_page_types === null || in_array($pageType, $wt->allowed_page_types, true))
            ->filter(function ($wt) use ($registry, $slotHandle) {
                if ($slotHandle === null) {
                    return true;
                }
                $def = $registry->find($wt->handle);
                return $def && in_array($slotHandle, $def->allowedSlots(), true);
            })
            ->map(fn ($wt) => [
                'id'              => $wt->id,
                'handle'          => $wt->handle,
                'label'           => $wt->label,
                'description'     => $wt->description,
                'category'        => $wt->category ?? ['content'],
                'collections'     => $wt->collections,
                'config_schema'   => $wt->config_schema,
                'assets'          => $wt->assets ?? [],
                'full_width'      => $wt->full_width,
                'default_open'    => $wt->default_open,
                'required_config' => $wt->required_config,
                'presets'         => static::resolvePresetThumbnails($wt->handle, $registry->find($wt->handle)?->presets() ?? []),
                'draft_presets'   => $wt->draftPresets->map(fn ($p) => [
                    'id'                => $p->id,
                    'handle'            => $p->handle,
                    'label'             => $p->label,
                    'description'       => $p->description,
                    'config'            => $p->config ?? [],
                    'appearance_config' => $p->appearance_config ?? [],
                    'is_draft'          => true,
                ])->values()->toArray(),
                'thumbnail'       => $wt->getFirstMediaUrl('thumbnail', 'picker') ?: static::resolveStaticThumbnail($wt->handle),
                'thumbnail_hover' => $wt->getFirstMediaUrl('thumbnail_hover', 'picker') ?: null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Attach a `thumbnail` key to each code-authored preset, resolving to a
     * public widget-thumbnails URL when the PNG exists on disk and null otherwise.
     *
     * @param  array<int, array<string, mixed>>  $presets
     * @return array<int, array<string, mixed>>
     */
    public static function resolvePresetThumbnails(string $widgetHandle, array $presets): array
    {
        $def = app(\App\Services\WidgetRegistry::class)->find($widgetHandle);
        $folder = $def ? Str::beforeLast(class_basename($def), 'Definition') : null;

        return array_map(function (array $preset) use ($widgetHandle, $folder) {
            $thumbnail = null;
            if ($folder && isset($preset['handle'])) {
                $file = 'preset-' . $preset['handle'] . '.png';
                $path = base_path("app/Widgets/{$folder}/thumbnails/{$file}");
                if (file_exists($path)) {
                    $thumbnail = route('widget-thumbnails.show', ['handle' => $widgetHandle, 'file' => $file])
                        . '?v=' . filemtime($path);
                }
            }
            $preset['thumbnail'] = $thumbnail;
            return $preset;
        }, $presets);
    }

    /**
     * Resolve the widget's `static.png` to a public widget-thumbnails URL when
     * the file exists on disk, or null otherwise.
     */
    public static function resolveStaticThumbnail(string $widgetHandle): ?string
    {
        $def = app(\App\Services\WidgetRegistry::class)->find($widgetHandle);
        if (! $def) {
            return null;
        }
        $folder = Str::beforeLast(class_basename($def), 'Definition');
        $path = base_path("app/Widgets/{$folder}/thumbnails/static.png");
        if (! file_exists($path)) {
            return null;
        }
        return route('widget-thumbnails.show', ['handle' => $widgetHandle, 'file' => 'static.png'])
            . '?v=' . filemtime($path);
    }
}
