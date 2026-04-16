<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemplateResource\Pages;
use App\Models\Template;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_page') || auth()->user()?->can('edit_theme_scss') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('update_page') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        return ($user?->can('update_page') || $user?->can('edit_theme_scss') || $user?->can('edit_site_chrome')) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user && ! $user->can('update_page')) {
            return false;
        }

        // Cannot delete the default page template
        return ! $record->is_default;
    }

    public static function form(Form $form): Form
    {
        // Form is only used for creating new page templates
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'page'    => 'Page',
                        'content' => 'Content',
                        default   => $state,
                    })
                    ->colors([
                        'primary' => 'page',
                        'info'    => 'content',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Template $record): string => $record->type === 'content'
                        ? Pages\EditContentTemplate::getUrl(['record' => $record])
                        : Pages\EditPageTemplate::getUrl(['record' => $record])
                    ),

                Tables\Actions\Action::make('setDefault')
                    ->label('Set Default')
                    ->icon('heroicon-m-star')
                    ->requiresConfirmation()
                    ->action(function (Template $record) {
                        abort_unless(auth()->user()?->can('update_page'), 403);
                        Template::page()->where('is_default', true)->update(['is_default' => false]);
                        $record->update(['is_default' => true]);
                    })
                    ->visible(fn (Template $record): bool => auth()->user()?->can('update_page') && $record->type === 'page' && ! $record->is_default),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Template $record): bool => $record->is_default),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'                => Pages\ListTemplates::route('/'),
            'create'               => Pages\CreateTemplate::route('/create'),
            'edit-content'         => Pages\EditContentTemplate::route('/{record}/edit-content'),
            'edit-page'            => Pages\EditPageTemplate::route('/{record}/edit-page'),
        ];
    }
}
