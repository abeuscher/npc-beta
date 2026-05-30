<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Concerns\HasPageSecondaryActions;
use App\Models\Template;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditPageDetails extends ReadOnlyAwareEditRecord
{
    use HasPageSecondaryActions;

    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return 'Page Details';
    }

    public function form(Form $form): Form
    {
        return $form->schema(
            PageResource::metadataFormSchema(
                type: 'page',
                modelType: 'page',
                tagType: 'page',
                extraTitleFields: [
                    Forms\Components\Placeholder::make('system_slug_display')
                        ->label('Slug')
                        ->content(fn ($record): string => $record?->slug ?? '—')
                        ->helperText('Slug is locked — system page slugs can only be changed via the System Pages Prefix setting.')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type')
                        ->label('Page Type')
                        ->options([
                            'default' => 'Web Page',
                            'member'  => 'Member Page',
                        ])
                        ->default('default')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('type_display')
                        ->label('Page Type')
                        ->content('System Page')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    // Edit lock — only holders of edit_locked_pages see or set it.
                    // Orthogonal to status: a locked page stays publicly visible
                    // ("Published & Locked"); the lock only bars editing.
                    Forms\Components\Toggle::make('locked')
                        ->label('Lock editing (Published & Locked)')
                        ->helperText('When on, only users with the “edit locked pages” permission can edit this page. The page stays publicly visible.')
                        ->visible(fn (): bool => auth()->user()?->can('edit_locked_pages') ?? false)
                        ->columnSpanFull(),
                ],
                templateField: Forms\Components\Select::make('template_id')
                    ->label('Page Template')
                    ->options(fn () => Template::page()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => Template::page()->where('is_default', true)->value('id'))
                    ->helperText('Header, footer, and styling.'),
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('post_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in listing widgets and social sharing.')
                        ->collection('post_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('og_image')
                        ->label('Open Graph image')
                        ->helperText('Used for social sharing previews.')
                        ->collection('og_image')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),
                ],
                withSeo: true,
            )
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editPage')
                ->label('Edit Page')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => PageResource::getUrl('edit', ['record' => $this->record])),

            $this->pageSecondaryActionsGroup(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ListPages::getUrl() => 'Pages',
            EditPage::getUrl(['record' => $this->record]) => 'Edit Page',
            'Page Details',
        ];
    }
}
