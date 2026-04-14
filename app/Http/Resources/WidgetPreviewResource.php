<?php

namespace App\Http\Resources;

use App\Models\PageWidget;
use App\Services\WidgetPreviewRenderer;
use Illuminate\Http\Request;

class WidgetPreviewResource extends WidgetResource
{
    public function toArray(Request $request): array
    {
        /** @var PageWidget $pw */
        $pw = $this->resource;

        return parent::toArray($request) + [
            'preview_html' => app(WidgetPreviewRenderer::class)->render($pw),
        ];
    }
}
