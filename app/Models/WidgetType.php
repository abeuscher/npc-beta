<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetType extends Model
{
    use HasFactory, HasUuids;

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
        'render_mode',
        'collections',
        'assets',
        'default_open',
        'config_schema',
        'template',
        'css',
        'js',
        'variable_name',
        'code',
    ];

    protected $casts = [
        'collections'   => 'array',
        'assets'        => 'array',
        'config_schema' => 'array',
        'default_open'  => 'boolean',
    ];

    public function pageWidgets(): HasMany
    {
        return $this->hasMany(PageWidget::class);
    }
}
