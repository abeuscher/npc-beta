<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $url  = $base . '/' . $this->record->slug;

        return [
            Actions\Action::make('publicUrl')
                ->label($url)
                ->url($url)
                ->openUrlInNewTab()
                ->link()
                ->color('primary')
                ->extraAttributes([
                    'style' => 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40vw;display:block;font-family:monospace;font-size:0.8125rem;',
                    'title' => $url,
                ]),

            Actions\DeleteAction::make(),
        ];
    }
}
