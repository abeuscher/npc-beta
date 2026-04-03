<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $casts = [
        'collections'        => 'array',
        'assets'             => 'array',
        'config_schema'      => 'array',
        'category'           => 'array',
        'default_open'       => 'boolean',
        'full_width'         => 'boolean',
        'allowed_page_types' => 'array',
    ];

    public function getDefaultConfig(): array
    {
        $config = [];
        foreach ($this->config_schema ?? [] as $field) {
            $config[$field['key']] = $field['default'] ?? match ($field['type'] ?? 'text') {
                'toggle' => false,
                'number' => null,
                'image'  => null,
                default  => '',
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
}
