<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        $typeLabel = match ($this->record->type) {
            'system' => 'System Page',
            'member' => 'Member Page',
            'post'   => 'Post',
            'event'  => 'Event Landing Page',
            default  => 'Page',
        };

        return 'Edit ' . $typeLabel;
    }

    protected function getHeaderActions(): array
    {
        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $path = $this->record->slug === 'home' ? '/' : '/' . $this->record->slug;
        $url  = $base . $path;

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

            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->type === 'system'),
        ];
    }
}
