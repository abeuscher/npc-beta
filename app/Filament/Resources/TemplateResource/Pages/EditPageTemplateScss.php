<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Filament\Resources\TemplateResource;
use App\Models\Template;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use ScssPhp\ScssPhp\Compiler;

class EditPageTemplateScss extends Page
{
    use HasRecordDetailSubNavigation, InteractsWithRecord {
        HasRecordDetailSubNavigation::getSubNavigation insteadof InteractsWithRecord;
    }

    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-page-template-scss';

    protected static ?string $title = 'SCSS';

    public string $themeScss = '';

    public string $buildOutput = '';

    public bool $buildSuccess = false;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->themeScss = $this->record->custom_scss ?? '';
    }

    public function getTitle(): string
    {
        return 'SCSS';
    }

    public function getBreadcrumb(): ?string
    {
        return 'SCSS';
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            EditPageTemplate::getUrl(['record' => $this->record]) => 'Edit Page Template',
            'SCSS',
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('edit_theme_scss') ?? false;
    }

    public function getIsNonDefaultProperty(): bool
    {
        return ! $this->record->is_default;
    }

    public function getDefaultTemplateProperty(): ?Template
    {
        return Template::page()->where('is_default', true)->first();
    }

    private function assertCanEdit(): void
    {
        abort_unless(auth()->user()?->can('edit_theme_scss'), 403);
    }

    public function clearScss(): void
    {
        $this->assertCanEdit();
        $this->record->update(['custom_scss' => null]);
        $this->themeScss = '';

        Notification::make()->title('SCSS reset to inherit from default')->success()->send();
    }

    public function saveAndBuildScss(): void
    {
        $this->assertCanEdit();
        $scss = $this->themeScss;

        try {
            $compiler = new Compiler();
            $compiler->compileString($scss);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('SCSS error')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $this->record->update(['custom_scss' => $scss]);

        if ($this->record->is_default) {
            file_put_contents(resource_path('scss/_custom.scss'), $scss);

            $projectRoot = base_path();
            $output = [];
            $exitCode = 0;
            exec("cd " . escapeshellarg($projectRoot) . " && ./node_modules/.bin/vite build 2>&1", $output, $exitCode);

            $this->buildOutput = implode("\n", $output);
            $this->buildSuccess = ($exitCode === 0);

            if ($this->buildSuccess) {
                Notification::make()->title('Theme built successfully.')->success()->send();
            } else {
                Notification::make()
                    ->title('Build failed')
                    ->body(substr($this->buildOutput, 0, 500))
                    ->danger()
                    ->send();
            }
        } else {
            $this->buildOutput = '';
            Notification::make()->title('SCSS saved')->success()->send();
        }
    }

    protected function subNavigationEntryPage(): ?string
    {
        return EditPageTemplate::class;
    }

    protected function additionalSubNavigationPages(): array
    {
        return [
            EditPageTemplateScss::class,
        ];
    }

    protected function recordDetailViewSubPageClass(): ?string
    {
        return EditPageTemplateChrome::class;
    }
}
