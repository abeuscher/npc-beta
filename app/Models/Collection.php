<?php

namespace App\Models;

/*
 * Collections marked is_public = true will be queryable from public-facing page components
 * in session 007+. Never mark a collection as public if it contains personal, financial,
 * or membership-related data. CRM entities (Contacts, Memberships, Donations, Organizations)
 * are architecturally excluded from the collection system and cannot be surfaced publicly
 * through this mechanism regardless of settings.
 *
 * Payment data is strictly one-directional: CMS → Payment → CRM. Financial transaction
 * records, registrant data, and donor history must never be queried from or displayed by
 * the CMS layer under any circumstances. System collections (blog_posts, events) expose
 * display-only data shapes; their underlying financial and relational records remain
 * exclusively in the CRM and payment systems.
 *
 * Collections are not routable. They have no URL relationship. The 'handle' is a unique
 * machine identifier used to reference a collection in widgets — not a URL segment.
 */

use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Collection extends Model
{
    use HasFactory, HasSlug, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'handle',
        'description',
        'fields',
        'source_type',
        'is_public',
        'is_active',
    ];

    protected $casts = [
        'fields'     => 'array',
        'is_public'  => 'boolean',
        'is_active'  => 'boolean',
    ];

    // Reserved handles that map to system source types.
    // Users cannot create custom collections with these handles.
    public const RESERVED_HANDLES = ['blog_posts', 'events', 'products'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('handle')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class);
    }

    /**
     * Scope: only public, active collections. Session 007 will extend this to accept
     * filter parameters (field-level filters, limit, order) for widget queries.
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true)->where('is_active', true);
    }

    /**
     * Returns true when this collection is backed by a system source type rather than
     * the generic collection_items JSONB store.
     */
    public function isSystemCollection(): bool
    {
        return $this->source_type !== 'custom';
    }

    /**
     * Converts the fields array into a Filament form schema, used by CollectionItemResource
     * to build the dynamic edit form for items belonging to this collection.
     *
     * Each field definition maps to a Filament component keyed under data.{field_key}
     * so values are stored nested inside the data JSONB column.
     */
    public function getFormSchema(): array
    {
        $schema = [];

        foreach ($this->fields ?? [] as $field) {
            $key      = $field['key'] ?? '';
            $label    = $field['label'] ?? $key;
            $required = (bool) ($field['required'] ?? false);
            $helpText = $field['helpText'] ?? '';
            $type     = $field['type'] ?? 'text';
            $options  = $field['options'] ?? [];

            $component = match ($type) {
                'textarea'  => Forms\Components\Textarea::make("data.{$key}"),
                'rich_text' => Forms\Components\RichEditor::make("data.{$key}"),
                'number'    => Forms\Components\TextInput::make("data.{$key}")->numeric(),
                'date'      => Forms\Components\DatePicker::make("data.{$key}"),
                'toggle'    => Forms\Components\Toggle::make("data.{$key}"),
                'image'     => Forms\Components\FileUpload::make("data.{$key}")->image(),
                'url'       => Forms\Components\TextInput::make("data.{$key}")->url(),
                'email'     => Forms\Components\TextInput::make("data.{$key}")->email(),
                'select'    => Forms\Components\Select::make("data.{$key}")
                                   ->options(collect($options)->pluck('label', 'value')->all()),
                default     => Forms\Components\TextInput::make("data.{$key}"),
            };

            $component->label($label)
                      ->required($required)
                      ->helperText($helpText);

            $schema[] = $component;
        }

        return $schema;
    }
}
