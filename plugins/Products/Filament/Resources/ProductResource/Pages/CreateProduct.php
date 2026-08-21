<?php

namespace Plugins\Products\Filament\Resources\ProductResource\Pages;

use Plugins\Products\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['name']);
        $slug = $base;
        $i    = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $data['slug'] = $slug;

        return $data;
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl() => 'Products',
            'Create',
        ];
    }
}
