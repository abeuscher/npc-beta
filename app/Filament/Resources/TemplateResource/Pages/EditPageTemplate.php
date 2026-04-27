<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;
use App\Filament\Resources\TemplateResource;
use App\Models\Template;
use App\Services\ImportExport\ContentExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditPageTemplate extends ReadOnlyAwareEditRecord
{
    use HasRecordDetailSubNavigation;

    protected static string $resource = TemplateResource::class;

    protected static string $view = 'filament.resources.template-resource.pages.edit-page-template';

    protected static ?string $title = 'Label and Colors';

    public function getTitle(): string
    {
        return $this->record->name ?? 'Edit Page Template';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(1000),
            ])->columnSpan(6),

            Forms\Components\Section::make('Colors')
                ->schema([
                    Forms\Components\ColorPicker::make('primary_color')
                        ->label('Brand')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('header_bg_color')
                        ->label('Header bg')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_link_color')
                        ->label('Nav link')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_hover_color')
                        ->label('Nav hover')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('nav_active_color')
                        ->label('Nav active')
                        ->columnSpan(2),

                    Forms\Components\ColorPicker::make('footer_bg_color')
                        ->label('Footer bg')
                        ->columnSpan(2),
                ])
                ->columns(12)
                ->columnSpanFull(),
        ])->columns(12);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportTemplate')
                ->label('Export Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                ->action(function (): StreamedResponse {
                    abort_unless(auth()->user()?->can('update_page'), 403);

                    $bundle    = app(ContentExporter::class)->exportTemplates([$this->record->id]);
                    $nameSlug  = Str::slug($this->record->name);
                    $filename  = now()->format('Ymd-His') . '-template-' . $nameSlug . '.json';

                    return response()->streamDownload(
                        fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->is_default),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TemplateResource::getUrl() => 'Templates',
            'Edit Page Template',
            'Label and Colors',
        ];
    }

    public function getIsNonDefaultProperty(): bool
    {
        return ! $this->record->is_default;
    }

    public function getDefaultTemplateProperty(): ?Template
    {
        return Template::page()->where('is_default', true)->first();
    }

    public function clearAppearance(): void
    {
        abort_unless(auth()->user()?->can('update_page'), 403);
        $this->record->update([
            'primary_color'    => null,
            'header_bg_color'  => null,
            'nav_link_color'   => null,
            'nav_hover_color'  => null,
            'nav_active_color' => null,
            'footer_bg_color'  => null,
        ]);

        $this->fillForm();

        Notification::make()->title('Appearance reset to inherit from default')->success()->send();
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
