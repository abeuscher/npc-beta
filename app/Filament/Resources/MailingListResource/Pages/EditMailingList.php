<?php

namespace App\Filament\Resources\MailingListResource\Pages;

use App\Filament\Resources\MailingListResource;
use App\Filament\Resources\MailingListResource\Widgets\MailingListMembersWidget;
use App\Models\MailingList;
use App\Services\MailChimpService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMailingList extends EditRecord
{
    protected static string $resource = MailingListResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            MailingListMembersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                    return MailingListResource::streamCsvExport($this->record);
                }),

            Actions\Action::make('syncMailchimp')
                ->label('Sync to MailChimp')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync to MailChimp')
                ->modalDescription('This will push all contactable members of this list to your MailChimp audience and apply the list tag. The sync runs in the background — large lists may take a minute to appear in MailChimp.')
                ->visible(fn () => app(MailChimpService::class)->isConfigured())
                ->action(function (MailingList $record): void {
                    app(MailChimpService::class)->syncList($record);
                    Notification::make()
                        ->title('Sync submitted to MailChimp')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
