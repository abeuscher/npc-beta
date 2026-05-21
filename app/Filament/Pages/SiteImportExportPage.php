<?php

namespace App\Filament\Pages;

use App\Filament\Actions\ImportBundleAction;
use App\Jobs\ExportBundleJob;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Template;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;

/**
 * Session 310 rollup: a single Tools-group page that exposes the unified
 * Export Site / Import Site pair. Built on top of the session-309 analyzer +
 * opt-flag plumbing; adds no new payload shape. Permission gate is
 * manage_cms_settings (heavier blast radius than per-entity import — the
 * rollup writes theme + media + templates + pages in one operation).
 */
class SiteImportExportPage extends FilamentPage
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import / Export Site';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.site-import-export';

    protected static ?string $title = 'Import / Export Site';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_cms_settings') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => 'Import / Export Site',
            'Tools',
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    public function getSnapshotCounts(): array
    {
        $designKeys = 0;
        foreach (['theme_colors', 'typography', 'button_styles'] as $key) {
            if (is_array(SiteSetting::get($key))) {
                $designKeys++;
            }
        }

        return [
            'pages'        => Page::count(),
            'templates'    => Template::count(),
            'design_keys'  => $designKeys,
            'media_count'  => Media::count(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->exportSiteAction(),
            ImportBundleAction::make(
                ability: 'manage_cms_settings',
                name: 'importSite',
                label: 'Import Site',
            )
                ->modalHeading('Import Site')
                ->modalDescription('Upload a site bundle (JSON or .zip) — typically one produced by Export Site on this install or another. The bundle is analysed on upload so you can review and adjust which parts to apply before clicking Import. Large bundles are imported in the background.')
                ->extraAttributes(['data-testid' => 'site-import-action']),
        ];
    }

    protected function exportSiteAction(): Action
    {
        return Action::make('exportSite')
            ->label('Export Site')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->visible(fn () => auth()->user()?->can('manage_cms_settings') ?? false)
            ->requiresConfirmation()
            ->modalHeading('Export Site')
            ->modalDescription(function (): string {
                $c = $this->getSnapshotCounts();
                $bits = [
                    $c['pages'] . ' page' . ($c['pages'] === 1 ? '' : 's'),
                    $c['templates'] . ' template' . ($c['templates'] === 1 ? '' : 's'),
                    'theme (' . $c['design_keys'] . ' design key' . ($c['design_keys'] === 1 ? '' : 's') . ')',
                    $c['media_count'] . ' media file' . ($c['media_count'] === 1 ? '' : 's'),
                ];

                return 'A full site snapshot will be built in the background: ' . implode(', ', $bits) . '. You will be notified when the bundle is ready to download.';
            })
            ->modalSubmitActionLabel('Export Site')
            ->extraAttributes(['data-testid' => 'site-export-action'])
            ->action(function (): void {
                abort_unless(auth()->user()?->can('manage_cms_settings'), 403);

                ExportBundleJob::dispatch(
                    'site',
                    [],
                    (int) auth()->id(),
                    'site-snapshot',
                    ['with_design' => true, 'with_media' => true],
                );

                Notification::make()
                    ->title('Export queued')
                    ->body('Your site bundle is being built in the background. You will be notified when it is ready to download.')
                    ->success()
                    ->send();
            });
    }
}
