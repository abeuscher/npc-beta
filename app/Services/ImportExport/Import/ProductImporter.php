<?php

namespace App\Services\ImportExport\Import;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\ImportExport\ImportLog;
use Illuminate\Support\Facades\Storage;

/**
 * Imports a serialized product: upsert by slug, replace its price list
 * wholesale, and rewire its single product_image media collection from the
 * bundle's descriptor. Session A001.
 */
class ProductImporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data, ImportLog $log, BundleMediaArchive $archive): void
    {
        $productData = $data['product'] ?? null;
        $slug        = $productData['slug'] ?? null;

        if (! is_array($productData) || ! is_string($slug) || $slug === '') {
            $log->warning('Product entry missing product.slug, skipped.');

            return;
        }

        $attributes = [
            'name'         => $productData['name'] ?? 'Untitled',
            'slug'         => $slug,
            'description'  => $productData['description'] ?? null,
            'capacity'     => $productData['capacity'] ?? 0,
            'status'       => $productData['status'] ?? 'draft',
            'sort_order'   => $productData['sort_order'] ?? 0,
            'is_archived'  => (bool) ($productData['is_archived'] ?? false),
            'published_at' => ! empty($productData['published_at'])
                ? \Carbon\Carbon::parse($productData['published_at'])
                : null,
        ];

        $product = Product::updateOrCreate(['slug' => $slug], $attributes);

        // Prices are canonical from the bundle. Wipe and re-create so a
        // dropped tier in the source doesn't leave a stale tier on the target.
        ProductPrice::where('product_id', $product->id)->delete();
        foreach ($data['prices'] ?? [] as $sortIndex => $priceRow) {
            if (! is_array($priceRow)) {
                continue;
            }
            ProductPrice::create([
                'product_id'      => $product->id,
                'label'           => $priceRow['label'] ?? 'Price',
                'amount'          => $priceRow['amount'] ?? 0,
                'stripe_price_id' => $priceRow['stripe_price_id'] ?? null,
                'sort_order'      => (int) ($priceRow['sort_order'] ?? $sortIndex),
            ]);
        }

        $this->rewireProductMedia($product, $data['media'] ?? [], $log, $archive);
    }

    /**
     * Mirrors PageImporter::rewirePageMedia() for the product_image single-file collection.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    protected function rewireProductMedia(Product $product, array $descriptors, ImportLog $log, BundleMediaArchive $archive): void
    {
        if (empty($descriptors)) {
            return;
        }

        $product->clearMediaCollection('product_image');

        foreach ($descriptors as $desc) {
            $collectionName = $desc['collection_name'] ?? null;
            $disk           = $desc['disk'] ?? 'public';
            $path           = $desc['path'] ?? null;

            if (! $collectionName || ! $path) {
                $log->warning("Product \"{$product->slug}\": media descriptor missing collection/path, skipped.");

                continue;
            }

            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                $log->warning("Product \"{$product->slug}\": media descriptor for collection '{$collectionName}' has unsafe path, skipped.");

                continue;
            }

            $archiveAbs = $archive->archiveFile($path);
            if ($archiveAbs !== null) {
                $product
                    ->addMedia($archiveAbs)
                    ->preservingOriginal()
                    ->usingFileName(basename($path))
                    ->toMediaCollection($collectionName, $disk);
            } elseif (Storage::disk($disk)->exists($path)) {
                $product
                    ->addMediaFromDisk($path, $disk)
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName, $disk);
            } else {
                $log->warning("Product \"{$product->slug}\": media file for collection '{$collectionName}' not found at '{$path}' on disk '{$disk}', skipped.");

                continue;
            }
        }
    }
}
