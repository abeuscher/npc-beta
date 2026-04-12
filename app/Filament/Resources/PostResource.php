<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Page;
use App\Services\ImportExport\ContentExporter;
use App\Traits\HasPageBuilderForm;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostResource extends Resource
{
    use HasPageBuilderForm;

    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Blog Posts';

    protected static ?string $modelLabel = 'Post';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('type', 'post');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_post') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(
            static::metadataFormSchema(
                type: 'post',
                modelType: 'page',
                tagType: 'post',
                extraTitleFields: [
                    Forms\Components\Hidden::make('type')
                        ->default('post'),
                ],
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('post_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in blog listing widgets and social sharing.')
                        ->collection('post_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('post_header')
                        ->label('Header image')
                        ->helperText('Optional banner image displayed at the top of the post.')
                        ->collection('post_header')
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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Export selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn () => auth()->user()?->can('update_page') ?? false)
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): StreamedResponse {
                            abort_unless(auth()->user()?->can('update_page'), 403);

                            $ids      = $records->pluck('id')->all();
                            $bundle   = app(ContentExporter::class)->exportPages($ids);
                            $filename = now()->format('Ymd-His') . '-posts-' . count($ids) . '.json';

                            return response()->streamDownload(
                                fn () => print(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                                $filename,
                                ['Content-Type' => 'application/json'],
                            );
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListPosts::route('/'),
            'create'  => Pages\CreatePost::route('/create'),
            'edit'    => Pages\EditPost::route('/{record}/edit'),
            'details' => Pages\EditPostDetails::route('/{record}/details'),
        ];
    }
}
