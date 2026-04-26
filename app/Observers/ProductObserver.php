<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    public function creating(Product $product): void
    {
        if ($product->status === 'published'
            && ! $product->is_archived
            && $product->published_at === null
        ) {
            $product->published_at = now();
        }
    }

    public function updating(Product $product): void
    {
        if ($product->isDirty(['status', 'is_archived'])
            && $product->status === 'published'
            && ! $product->is_archived
            && $product->published_at === null
        ) {
            $product->published_at = now();
        }
    }
}
