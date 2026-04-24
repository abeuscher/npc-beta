<?php

namespace App\Http\Resources;

use App\Models\PageWidget;
use App\Services\WidgetPreviewRenderer;
use Illuminate\Http\Request;

class WidgetPreviewResource extends WidgetResource
{
    public string $slotHandle = 'page_builder_canvas';

    public function withSlotHandle(string $slotHandle): static
    {
        $this->slotHandle = $slotHandle;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var PageWidget $pw */
        $pw = $this->resource;

        return parent::toArray($request) + [
            'preview_html' => app(WidgetPreviewRenderer::class)->render($pw, $this->slotHandle),
        ];
    }
}
