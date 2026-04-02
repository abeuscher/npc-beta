<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Models\Template;
use App\Traits\HasPageBuilderForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PageResource extends Resource
{
    use HasPageBuilderForm;

    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_page') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->type !== 'system' && (auth()->user()?->can('delete_page') ?? false);
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_page') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(
            static::pageBuilderFormSchema(
                type: 'page',
                modelType: 'page',
                tagType: 'page',
                extraTitleFields: [
                    // System pages: show the full stored slug as read-only text.
                    Forms\Components\Placeholder::make('system_slug_display')
                        ->label('Slug')
                        ->content(fn ($record): string => $record?->slug ?? '—')
                        ->helperText('Slug is locked — system page slugs can only be changed via the System Pages Prefix setting.')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),

                    // Shown on create only — editable type selection.
                    Forms\Components\Select::make('type')
                        ->label('Page Type')
                        ->options([
                            'default' => 'Web Page',
                            'member'  => 'Member Page',
                        ])
                        ->default('default')
                        ->hiddenOn('edit')
                        ->columnSpanFull(),

                    // Shown on edit only for system pages — read-only type label.
                    Forms\Components\Placeholder::make('type_display')
                        ->label('Page Type')
                        ->content('System Page')
                        ->visibleOn('edit')
                        ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system')
                        ->columnSpanFull(),
                ],
                templateSection: Forms\Components\Section::make('Templates')
                    ->schema([
                        // Page template — selectable on create and edit.
                        Forms\Components\Select::make('template_id')
                            ->label('Page Template')
                            ->options(fn () => Template::page()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
                            ->default(fn () => Template::page()->where('is_default', true)->value('id'))
                            ->helperText('Header, footer, and styling.')
                            ->columnSpanFull(),

                        // Content template — create only, used to prepopulate widgets.
                        Forms\Components\Select::make('content_template_id')
                            ->label('Content Template')
                            ->options(fn () => collect(['' => 'Blank'])->merge(Template::content()->orderBy('name')->pluck('name', 'id')))
                            ->default('')
                            ->helperText('Widget preset — applied once at creation.')
                            ->hiddenOn('edit')
                            ->columnSpanFull(),
                    ]),
                withSeo: true,
                pageBuilderProps: fn ($record) => ['pageId' => $record->id],
            )
        );
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('type', '!=', 'event')
            ->where('type', '!=', 'post');
    }

    public static function resolveRecordRouteBinding(int | string $key): ?\Illuminate\Database\Eloquent\Model
    {
        // getEloquentQuery() excludes event/post pages to keep the CMS list clean,
        // but that scope also blocks direct edits of event landing pages.
        // Resolve records without the type filter so any page type can be edited directly.
        return \App\Models\Page::where('id', $key)->first();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray'    => 'default',
                        'info'    => 'post',
                        'warning' => 'event',
                        'success' => 'member',
                        'danger'  => 'system',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                        default   => 'Page',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'published',
                    ]),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->multiple()
                    ->options([
                        'default' => 'Page',
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                    ])
                    ->default(['default']),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system')
                    ->modalDescription(fn (Page $record): ?string => match ($record->type) {
                        'member' => 'Warning: Deleting this page may render the member portal unusable. Are you sure you want to proceed?',
                        default  => null,
                    }),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system')
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->modalDescription(fn (Page $record): ?string => match ($record->type) {
                        'member' => 'Warning: Permanently deleting this page may render the member portal unusable.',
                        default  => null,
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if ($record->type !== 'system') {
                                    $record->delete();
                                }
                            });
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if ($record->type !== 'system') {
                                    $record->forceDelete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
