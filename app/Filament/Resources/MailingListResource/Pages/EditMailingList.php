<?php

namespace App\Filament\Resources\MailingListResource\Pages;

use App\Filament\Resources\MailingListResource;
use App\Filament\Resources\MailingListResource\Widgets\MailingListMembersWidget;
use App\Models\MailingList;
use App\Services\MailChimpService;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditMailingList extends ReadOnlyAwareEditRecord
{
    protected static string $resource = MailingListResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            MailingListMembersWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
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
                ->hidden(fn () => ! auth()->user()?->can('update_mailing_list'))
                ->visible(fn () => app(MailChimpService::class)->isConfigured())
                ->action(function (MailingList $record): void {
                    abort_unless(auth()->user()?->can('update_mailing_list'), 403);
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
