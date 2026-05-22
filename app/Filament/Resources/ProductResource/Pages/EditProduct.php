<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditProduct extends ReadOnlyAwareEditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('duplicateProduct')
                    ->label('Duplicate Product')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn () => auth()->user()?->can('create_product') ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate product')
                    ->modalDescription('Creates a draft copy of this product, including its price tiers. The copy opens for editing.')
                    ->modalSubmitActionLabel('Duplicate')
                    ->action(function () {
                        abort_unless(auth()->user()?->can('create_product'), 403);

                        $copy = $this->record->duplicate();

                        Notification::make()
                            ->title('Product duplicated')
                            ->body('A draft copy was created. You are now editing the copy.')
                            ->success()
                            ->send();

                        return redirect(ProductResource::getUrl('edit', ['record' => $copy]));
                    }),

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
