<?php

namespace App\WidgetPrimitive\Views;

use App\Models\PageLayout;
use App\Models\PageWidget;
use App\WidgetPrimitive\IsView;
use Database\Factories\RecordDetailViewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RecordDetailView extends Model implements IsView
{
    use HasFactory, HasUuids;

    protected static function newFactory(): Factory
    {
        return RecordDetailViewFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (RecordDetailView $view) {
            PageWidget::where('owner_type', static::class)
                ->where('owner_id', $view->getKey())
                ->each(fn (PageWidget $w) => $w->delete());

            PageLayout::where('owner_type', static::class)
                ->where('owner_id', $view->getKey())
                ->delete();
        });
    }

    protected $fillable = [
        'handle',
        'record_type',
        'label',
        'sort_order',
        'layout_config',
    ];

    protected $casts = [
        'layout_config' => 'array',
    ];

    public function handle(): string
    {
        return $this->handle;
    }

    public function slotHandle(): string
    {
        return 'record_detail_sidebar';
    }

    public function recordType(): string
    {
        return $this->record_type;
    }

    /**
     * @return array<int, PageWidget>
     */
    public function widgets(): array
    {
        return $this->pageWidgets()
            ->where('is_active', true)
            ->with('widgetType')
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutConfig(): array
    {
        return $this->layout_config ?? [];
    }

    public function pageWidgets(): MorphMany
    {
        return $this->morphMany(PageWidget::class, 'owner');
    }
}
