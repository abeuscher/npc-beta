<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_transactions')
                ->label('View transactions →')
                ->color('gray')
                ->url(fn () => TransactionResource::getUrl('index')
                    . '?tableFilters[product_id][value]=' . $this->record->getKey()),

            Actions\DeleteAction::make(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ProductResource::getUrl() => 'Products',
            $this->record->name,
        ];
    }
}
