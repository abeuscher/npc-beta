<?php

namespace App\Filament\Actions;

use App\Jobs\ImportBundleJob;
use App\Services\ImportExport\BundleArchive;
use App\Services\ImportExport\ContentImporter;
use App\Services\ImportExport\InvalidImportBundleException;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ImportBundleAction
{
    /**
     * "Import bundle" header action used on ListPages, ListPosts, and
     * ListTemplates. Single-page live-reveal flow (session 309): the
     * FileUpload's afterStateUpdated hook runs ContentImporter::analyze() on
     * the uploaded bytes the moment the upload finishes, stashes the manifest
     * into form state, and the gated toggles reveal themselves below. The
     * modal's single "Import" button submits the chosen $opts to
     * ImportBundleJob.
     *
     * Toggles:
     *   - Import N pages              (default ON, gated on pages present)
     *   - Replace duplicate pages     (default OFF, gated on slug collisions)
     *   - Replace site theme          (default OFF, gated on design payload)
     *   - Include N media files       (default ON, gated on media present)
     */
    public static function make(): Action
    {
        return Action::make('importBundle')
            ->label('Import Bundle')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn () => auth()->user()?->can('update_page') ?? false)
            ->modalHeading('Import Content Bundle')
            ->modalDescription('Upload a JSON bundle or a self-contained .zip (with media) exported from this admin. The bundle is analysed on upload — you can review and adjust which parts to apply before clicking Import. Large bundles are imported in the background.')
            ->modalSubmitActionLabel('Import')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\FileUpload::make('bundle_file')
                    ->label('Bundle file (JSON or .zip, up to 512 MB)')
                    ->required()
                    ->acceptedFileTypes([
                        'application/json',
                        'text/plain',
                        'text/json',
                        'application/zip',
                        'application/x-zip-compressed',
                        'multipart/x-zip',
                    ])
                    ->maxSize(524288)
                    ->disk('local')
                    ->directory('imports/bundles')
                    ->visibility('private')
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $absPath = self::resolveUploadedAbsPath($state);
                        if ($absPath === null) {
                            // File was cleared / removed, or upload didn't land —
                            // reset manifest so the gated fields hide again.
                            $set('manifest', []);

                            return;
                        }

                        try {
                            $manifest = app(ContentImporter::class)->analyze(self::loadEnvelope($absPath));
                        } catch (InvalidImportBundleException $e) {
                            Notification::make()
                                ->title('Bundle rejected')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            $set('manifest', []);

                            return;
                        }

                        $set('manifest', $manifest);

                        // Seed the toggles with their safe defaults — same values
                        // the service would use if the operator submits without
                        // touching anything.
                        $set('import_pages', true);
                        $set('replace_duplicate_pages', false);
                        $set('merge_design', false);
                        $set('import_media', true);
                    })
                    ->extraAttributes(['data-testid' => 'import-bundle-upload']),

                Forms\Components\Placeholder::make('manifest_summary')
                    ->label('Bundle contents')
                    ->content(fn (Get $get): string => self::manifestSummary($get('manifest') ?? []))
                    ->visible(fn (Get $get): bool => ! empty($get('manifest')))
                    ->extraAttributes(['data-testid' => 'import-bundle-manifest-summary']),

                Forms\Components\Toggle::make('import_pages')
                    ->label(fn (Get $get): string => 'Import ' . self::pageCount($get('manifest') ?? []) . ' pages')
                    ->helperText('Uncheck to skip all page entries — neither new pages nor updates to existing pages will be imported.')
                    ->visible(fn (Get $get): bool => self::pageCount($get('manifest') ?? []) > 0)
                    ->live()
                    ->extraAttributes(['data-testid' => 'import-bundle-opt-import-pages']),

                Forms\Components\Toggle::make('replace_duplicate_pages')
                    ->label('Replace duplicate pages')
                    ->helperText(fn (Get $get): string => self::duplicateHelper($get('manifest') ?? []))
                    ->visible(fn (Get $get): bool => self::hasDuplicatePages($get('manifest') ?? []))
                    ->disabled(fn (Get $get): bool => ! (bool) $get('import_pages'))
                    ->extraAttributes(['data-testid' => 'import-bundle-opt-replace-duplicates']),

                Forms\Components\Toggle::make('merge_design')
                    ->label('Replace site theme')
                    ->helperText('This bundle includes a theme payload. Importing will merge it over your current Theme settings.')
                    ->visible(fn (Get $get): bool => (bool) data_get($get('manifest') ?? [], 'has_design', false))
                    ->extraAttributes(['data-testid' => 'import-bundle-opt-merge-design']),

                Forms\Components\Toggle::make('import_media')
                    ->label(fn (Get $get): string => 'Include ' . (int) data_get($get('manifest') ?? [], 'media_count', 0) . ' media files')
                    ->visible(fn (Get $get): bool => (bool) data_get($get('manifest') ?? [], 'has_media', false))
                    ->extraAttributes(['data-testid' => 'import-bundle-opt-import-media']),

                Forms\Components\Hidden::make('manifest'),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('update_page'), 403);

                $relativePath = $data['bundle_file'] ?? null;
                if (is_array($relativePath)) {
                    $relativePath = reset($relativePath) ?: null;
                }
                if (! is_string($relativePath) || ! Storage::disk('local')->exists($relativePath)) {
                    Notification::make()->title('Upload failed')->danger()->send();

                    return;
                }

                $manifest = is_array($data['manifest'] ?? null) ? $data['manifest'] : [];

                $opts = [
                    'merge_design' => self::optFromState($data, 'merge_design', false)
                        && (bool) data_get($manifest, 'has_design', false),
                    'import_media' => self::optFromState($data, 'import_media', true)
                        && (bool) data_get($manifest, 'has_media', false),
                    'import_pages' => self::optFromState($data, 'import_pages', true)
                        || self::pageCount($manifest) === 0,
                    'replace_duplicate_pages' => self::optFromState($data, 'replace_duplicate_pages', false)
                        || ! self::hasDuplicatePages($manifest),
                ];

                ImportBundleJob::dispatch($relativePath, (int) auth()->id(), $opts);

                Notification::make()
                    ->title('Import queued')
                    ->body('Your bundle is being imported in the background. You will be notified when it completes.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Filament's FileUpload state during the afterStateUpdated hook is the
     * Livewire TemporaryUploadedFile (the file has been uploaded but the
     * "move to disk('local')->directory('imports/bundles')" finalization only
     * runs on the final form submission). Resolve the absolute path of the
     * bytes-on-disk for whichever shape we receive — temp upload object
     * during the live analyze, finalized relative path string on the
     * action() callback.
     */
    protected static function resolveUploadedAbsPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = reset($state) ?: null;
        }

        if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $abs = $state->getRealPath();

            return is_string($abs) && is_file($abs) ? $abs : null;
        }

        if (is_string($state) && $state !== '') {
            return Storage::disk('local')->exists($state)
                ? Storage::disk('local')->path($state)
                : null;
        }

        return null;
    }

    /**
     * Load the envelope from an uploaded path — zip or JSON, mirroring
     * ImportBundleJob's detection. The zip path extracts to a temp dir just to
     * read bundle.json, then cleans up; we never persist the extraction at the
     * analyze stage.
     *
     * @return array<string, mixed>
     */
    protected static function loadEnvelope(string $absPath): array
    {
        $fh = fopen($absPath, 'rb');
        if ($fh === false) {
            throw new InvalidImportBundleException('Bundle file could not be opened.');
        }
        $magic = fread($fh, 4);
        fclose($fh);
        $isZip = $magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08";

        if (! $isZip) {
            $bundle = json_decode((string) file_get_contents($absPath), true);
            if (! is_array($bundle)) {
                throw new InvalidImportBundleException('File is not a valid JSON bundle or zip.');
            }

            return $bundle;
        }

        $extracted = app(BundleArchive::class)->extract($absPath);

        // We only need the envelope to render the manifest — drop the temp dir
        // immediately; the queued ImportBundleJob will re-extract from the
        // original upload when the operator submits.
        try {
            return $extracted['envelope'];
        } finally {
            self::rmrf($extracted['tempDir']);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected static function manifestSummary(array $manifest): string
    {
        if (empty($manifest)) {
            return 'Upload a bundle to see what it contains.';
        }

        $pages     = (int) (is_array($manifest['pages'] ?? null) ? count($manifest['pages']) : 0);
        $templates = (int) (is_array($manifest['templates'] ?? null) ? count($manifest['templates']) : 0);
        $bits      = [];

        $bits[] = "{$pages} page" . ($pages === 1 ? '' : 's');
        $bits[] = "{$templates} template" . ($templates === 1 ? '' : 's');
        if (! empty($manifest['has_design'])) {
            $keys   = is_array($manifest['design_keys'] ?? null) ? $manifest['design_keys'] : [];
            $bits[] = 'theme payload (' . implode(', ', $keys) . ')';
        }
        if (! empty($manifest['has_media'])) {
            $bits[] = (int) ($manifest['media_count'] ?? 0) . ' media files';
        }

        return 'Bundle contains: ' . implode(', ', $bits) . '.';
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected static function pageCount(array $manifest): int
    {
        return is_array($manifest['pages'] ?? null) ? count($manifest['pages']) : 0;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected static function hasDuplicatePages(array $manifest): bool
    {
        foreach ($manifest['pages'] ?? [] as $row) {
            if (! empty($row['exists_locally'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected static function duplicateHelper(array $manifest): string
    {
        $dupes = 0;
        foreach ($manifest['pages'] ?? [] as $row) {
            if (! empty($row['exists_locally'])) {
                $dupes++;
            }
        }

        return "{$dupes} page" . ($dupes === 1 ? '' : 's') . ' in this bundle already exist on this site by slug. Replace them?';
    }

    /**
     * Resolve a toggle's state to a boolean. Filament Toggle state is
     * true / false / null; the explicit-default form keeps the path
     * predictable.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function optFromState(array $data, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        return (bool) $data[$key];
    }

    private static function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
