<?php

namespace App\Filament\Pages;

use App\Services\Media\MediaFinderService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tools-group scanner that finds duplicate and unused media for operator
 * triage. Both scans are read-only and run synchronously on demand; the only
 * mutation is the per-row delete action, which is operator-confirmed. Gate
 * mirrors SiteImportExportPage (manage_cms_settings).
 */
class MediaFinderPage extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Media Finder';

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.media-finder';

    protected static ?string $title = 'Media Finder';

    /** @var array<int, array<string, mixed>>|null */
    public ?array $unusedResults = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $duplicateResults = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $missingResults = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_cms_settings') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => 'Media Finder',
            'Tools',
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('runUnusedScan')
                ->label('Run unused scan')
                ->icon('heroicon-o-funnel')
                ->action(function (MediaFinderService $finder): void {
                    abort_unless(auth()->user()?->can('manage_cms_settings'), 403);
                    $this->unusedResults = $finder->scanUnused();

                    Notification::make()
                        ->title('Unused scan complete')
                        ->body(count($this->unusedResults) . ' unused candidate' . (count($this->unusedResults) === 1 ? '' : 's') . ' found.')
                        ->success()
                        ->send();
                }),

            Action::make('runDuplicateScan')
                ->label('Run duplicate scan')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (MediaFinderService $finder): void {
                    abort_unless(auth()->user()?->can('manage_cms_settings'), 403);
                    $this->duplicateResults = $finder->scanDuplicates();

                    Notification::make()
                        ->title('Duplicate scan complete')
                        ->body(count($this->duplicateResults) . ' duplicate cluster' . (count($this->duplicateResults) === 1 ? '' : 's') . ' found.')
                        ->success()
                        ->send();
                }),

            Action::make('runMissingScan')
                ->label('Run missing-file scan')
                ->icon('heroicon-o-exclamation-triangle')
                ->action(function (MediaFinderService $finder): void {
                    abort_unless(auth()->user()?->can('manage_cms_settings'), 403);
                    $this->missingResults = $finder->scanMissingFiles();

                    Notification::make()
                        ->title('Missing-file scan complete')
                        ->body(count($this->missingResults) . ' media row' . (count($this->missingResults) === 1 ? '' : 's') . ' with no file on disk.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function deleteMediaAction(): Action
    {
        return Action::make('deleteMedia')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading('Delete media file')
            ->modalDescription(function (array $arguments): string {
                $media = Media::find($arguments['media'] ?? null);
                $name = $media?->file_name ?? 'this file';

                return "Delete \"{$name}\"? This removes the file and its conversions from disk and cannot be undone.";
            })
            ->action(function (array $arguments): void {
                abort_unless(auth()->user()?->can('manage_cms_settings'), 403);

                $media = Media::find($arguments['media'] ?? null);
                if (! $media) {
                    Notification::make()->title('Media not found')->danger()->send();

                    return;
                }

                $id = (int) $media->id;
                $name = $media->file_name;
                $media->delete();

                $this->forgetMedia($id);

                Notification::make()
                    ->title('Media deleted')
                    ->body("\"{$name}\" was removed.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Drop a just-deleted media id from both result sets so the UI reflects the
     * change without re-running a scan.
     */
    private function forgetMedia(int $id): void
    {
        if (is_array($this->unusedResults)) {
            $this->unusedResults = array_values(array_filter(
                $this->unusedResults,
                fn (array $row) => $row['id'] !== $id,
            ));
        }

        if (is_array($this->duplicateResults)) {
            $clusters = [];
            foreach ($this->duplicateResults as $cluster) {
                $members = array_values(array_filter(
                    $cluster['members'],
                    fn (array $m) => $m['id'] !== $id,
                ));
                if (count($members) > 1) {
                    $cluster['members'] = $members;
                    $cluster['count'] = count($members);
                    $clusters[] = $cluster;
                }
            }
            $this->duplicateResults = $clusters;
        }

        if (is_array($this->missingResults)) {
            $this->missingResults = array_values(array_filter(
                $this->missingResults,
                fn (array $row) => $row['id'] !== $id,
            ));
        }
    }
}
