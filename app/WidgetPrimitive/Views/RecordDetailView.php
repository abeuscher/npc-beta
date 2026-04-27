<?php

namespace App\WidgetPrimitive\Views;

use App\Models\PageWidget;
use App\WidgetPrimitive\IsView;

final class RecordDetailView implements IsView
{
    /**
     * @param  string  $handle
     * @param  string  $recordType  FQCN of the bound record model (e.g. Contact::class).
     * @param  array<int, PageWidget>  $widgets
     * @param  array<string, mixed>  $layoutConfig
     */
    public function __construct(
        private readonly string $handle,
        private readonly string $recordType,
        private readonly array $widgets,
        private readonly array $layoutConfig = [],
    ) {}

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
        return $this->recordType;
    }

    /**
     * @return array<int, PageWidget>
     */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutConfig(): array
    {
        return $this->layoutConfig;
    }
}
