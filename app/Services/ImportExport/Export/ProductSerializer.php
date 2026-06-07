<?php

namespace App\Services\ImportExport\Export;

use App\Models\Product;

/**
 * Serializes Product rows (with their price list and single product_image
 * media descriptor) into the bundle's portable product shape. Session A001.
 */
class ProductSerializer
{
    /**
     * @param  array<int, string>  $productIds
     * @return array<int, array<string, mixed>>
     */
    public function serializeMany(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return Product::whereIn('id', $productIds)
            ->with(['prices' => fn ($q) => $q->orderBy('sort_order'), 'media'])
            ->get()
            ->map(fn (Product $p) => $this->serializeProduct($p))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeProduct(Product $product): array
    {
        return [
            'product' => [
                'name'         => $product->name,
                'slug'         => $product->slug,
                'description'  => $product->description,
                'capacity'     => $product->capacity,
                'status'       => $product->status,
                'sort_order'   => $product->sort_order,
                'is_archived'  => (bool) $product->is_archived,
                'published_at' => $product->published_at?->toIso8601String(),
            ],
            'prices' => $product->prices->map(fn ($price) => [
                'label'           => $price->label,
                'amount'          => $price->amount,
                'stripe_price_id' => $price->stripe_price_id,
                'sort_order'      => (int) $price->sort_order,
            ])->all(),
            'media' => $this->serializeProductMedia($product),
        ];
    }

    /**
     * Mirrors serializePageMedia() for the product_image single-file collection.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeProductMedia(Product $product): array
    {
        $descriptors = [];

        $media = $product->getFirstMedia('product_image');
        if ($media) {
            $descriptors[] = [
                'collection_name' => 'product_image',
                'file_name'       => $media->file_name,
                'disk'            => $media->disk,
                'id'              => $media->id,
                'path'            => $media->getPathRelativeToRoot(),
                'mime_type'       => $media->mime_type,
                'size'            => $media->size,
            ];
        }

        return $descriptors;
    }
}
