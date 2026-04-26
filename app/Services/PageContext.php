<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Form;
use App\Models\Page;
use App\Models\Product;

class PageContext
{
    public readonly ?Page $currentPage;
    public readonly mixed $currentUser;

    private array $collectionCache     = [];
    private array $eventCache          = [];
    private array $productCache        = [];
    private array $formCache           = [];

    public function __construct(?Page $currentPage = null)
    {
        $this->currentPage = $currentPage;
        $this->currentUser = auth('portal')->user();
    }

    public function collection(string $handle, ?int $limit = null): array
    {
        $key = $handle . ':' . ($limit ?? '');

        if (! array_key_exists($key, $this->collectionCache)) {
            $config = $limit !== null ? ['limit' => $limit] : [];
            $this->collectionCache[$key] = WidgetDataResolver::resolve($handle, $config);
        }

        return $this->collectionCache[$key];
    }

    public function event(?string $slug): ?Event
    {
        if ($slug === null) {
            return null;
        }

        if (! array_key_exists($slug, $this->eventCache)) {
            $this->eventCache[$slug] = Event::published()
                ->where('slug', $slug)
                ->first();
        }

        return $this->eventCache[$slug];
    }

    public function product(?string $slug): ?Product
    {
        if ($slug === null) {
            return null;
        }

        if (! array_key_exists($slug, $this->productCache)) {
            $this->productCache[$slug] = Product::where('slug', $slug)
                ->where('status', 'published')
                ->with('prices')
                ->first();
        }

        return $this->productCache[$slug];
    }

    public function form(string $handle): ?Form
    {
        if (! array_key_exists($handle, $this->formCache)) {
            $this->formCache[$handle] = Form::where('handle', $handle)
                ->where('is_active', true)
                ->first();
        }

        return $this->formCache[$handle];
    }
}
