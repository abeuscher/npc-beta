<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditProduct extends ReadOnlyAwareEditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('view_transactions')
                    ->label('View transactions →')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_transaction'))
                    ->url(fn () => TransactionResource::getUrl('index')
                        . '?tableFilters[product_id][value]=' . $this->record->getKey()),
            ]),
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
