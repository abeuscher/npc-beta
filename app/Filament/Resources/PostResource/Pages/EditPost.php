<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\SiteSetting;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPost extends ReadOnlyAwareEditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        $base    = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
        $url     = $base . '/' . $this->record->slug;
        $isDraft = $this->record->status !== 'published';

        return [
            $isDraft
                ? Actions\Action::make('publicUrl')
                    ->label($url)
                    ->link()
                    ->color('gray')
                    ->disabled()
                    ->extraAttributes([
                        'style' => 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40vw;display:block;font-family:monospace;font-size:0.8125rem;',
                        'title' => 'Post not published',
                    ])
                : Actions\Action::make('publicUrl')
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

            Actions\Action::make('exportPost')
                ->label('Export Post')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle   = app(ContentExporter::class)->exportPages([$this->record->id]);
                    $filename = now()->format('Ymd-His') . '-post-' . $this->record->slug . '.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),
        ];
    }
}
