<?php

namespace App\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\FormResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Illuminate\Support\Str;

class EditForm extends ReadOnlyAwareEditRecord
{
    protected static string $resource = FormResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            FormResource::getUrl() => 'Forms',
            'Edit Form',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_json')
                ->label('Download JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->hidden(fn () => ! auth()->user()?->can('update_form'))
                ->action(fn () => $this->downloadJson()),

            Actions\DeleteAction::make(),
        ];
    }

    public function downloadJson(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $record = $this->getRecord();

        $json = json_encode([
            'title'    => $record->title,
            'handle'   => $record->handle,
            'fields'   => $record->fields,
            'settings' => $record->settings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(
            fn () => print($json),
            Str::slug($record->handle) . '-form.json',
            ['Content-Type' => 'application/json']
        );
    }
}
