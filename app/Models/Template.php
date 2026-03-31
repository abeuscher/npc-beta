<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_default',
        'definition',
        'primary_color',
        'heading_font',
        'body_font',
        'header_bg_color',
        'footer_bg_color',
        'nav_link_color',
        'nav_hover_color',
        'nav_active_color',
        'custom_scss',
        'header_page_id',
        'footer_page_id',
        'created_by',
    ];

    protected $attributes = [
        'type'       => 'page',
        'is_default' => false,
    ];

    protected $casts = [
        'definition' => 'array',
        'is_default' => 'boolean',
    ];

    // ── Inheritable fields ──────────────────────────────────────────────────

    public const INHERITABLE_FIELDS = [
        'primary_color',
        'heading_font',
        'body_font',
        'header_bg_color',
        'footer_bg_color',
        'nav_link_color',
        'nav_hover_color',
        'nav_active_color',
        'custom_scss',
        'header_page_id',
        'footer_page_id',
    ];

    /**
     * Return this template's own value for $field if non-null,
     * otherwise fall back to the default template's value.
     */
    public function resolved(string $field): mixed
    {
        if ($this->is_default) {
            return $this->getAttribute($field);
        }

        $value = $this->getAttribute($field);

        if ($value !== null) {
            return $value;
        }

        return static::query()->default()->value($field);
    }

    /**
     * Create a system page for a custom header or footer, copying widgets from a source page.
     */
    public function createChromePage(string $position, ?string $sourcePageId = null): Page
    {
        $slug = "_{$position}_" . substr($this->id, 0, 8);

        $page = Page::create([
            'title'     => ucfirst($position) . ' — ' . $this->name,
            'slug'      => $slug,
            'type'      => 'system',
            'status'    => 'published',
            'author_id' => auth()->id(),
        ]);

        if ($sourcePageId) {
            PageWidget::copyBetweenPages($sourcePageId, $page->id);
        }

        return $page;
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeDefault($query): void
    {
        $query->where('is_default', true)->where('type', 'page');
    }

    public function scopePage($query): void
    {
        $query->where('type', 'page');
    }

    public function scopeContent($query): void
    {
        $query->where('type', 'content');
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function headerPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'header_page_id');
    }

    public function footerPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'footer_page_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
