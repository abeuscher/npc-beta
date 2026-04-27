<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordDetailViewResource\Pages;
use App\Models\Contact;
use App\Models\Template;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class RecordDetailViewResource extends Resource
{
    protected static ?string $model = RecordDetailView::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Record Detail Views';

    protected static ?string $modelLabel = 'Record Detail View';

    protected static ?string $pluralModelLabel = 'Record Detail Views';

    protected static ?int $navigationSort = 2;

    /**
     * Hardcoded record-type options. Extending the surface to a new record
     * type is a one-line edit here; no dynamic discovery in 5c.5.
     *
     * @return array<string, string>
     */
    public static function getRecordTypeOptions(): array
    {
        return [
            Contact::class => 'Contact',
        ];
    }

    /**
     * Seeded primary View handles per record type. The seeded primary is the
     * implicit foundation for a record type's front-page footer widgets; it
     * cannot be deleted through the admin UI because removing it would leave
     * the record type with no front-page composition surface and no UI path
     * to recreate one. Add an entry here when a new record type's primary
     * View is seeded by `RecordDetailViewSeeder`.
     *
     * @return array<class-string, array<int, string>>
     */
    public static function primaryHandles(): array
    {
        return [
            Contact::class => ['contact_overview'],
        ];
    }

    public static function isPrimary(RecordDetailView $view): bool
    {
        return in_array($view->handle, self::primaryHandles()[$view->record_type] ?? [], true);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage_record_detail_views') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_record_detail_views') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_record_detail_views') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if ($record instanceof RecordDetailView && self::isPrimary($record)) {
            return false;
        }

        return auth()->user()?->can('manage_record_detail_views') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $q) {
                $q->where('record_type', '!=', Template::class)
                    ->orWhere('handle', 'not like', 'page_template_%');
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('record_type')
                    ->label('Record Type')
                    ->options(self::getRecordTypeOptions())
                    ->required()
                    ->live()
                    ->helperText('The model that this View renders against. Adding new options is a code change.'),

                Forms\Components\TextInput::make('handle')
                    ->required()
                    ->maxLength(255)
                    ->rules(['alpha_dash'])
                    ->helperText('Per-record-type identifier, e.g. contact_overview. Lowercase, underscores allowed.')
                    ->unique(
                        table: 'record_detail_views',
                        column: 'handle',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule, Forms\Get $get): Unique {
                            return $rule->where('record_type', $get('record_type'));
                        },
                    ),

                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Display label used by the sub-nav primitive when more than one View is bound to the record type.'),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Lower numbers sort first within the record type.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('record_type')
                    ->label('Record Type')
                    ->formatStateUsing(fn (string $state) => self::getRecordTypeOptions()[$state] ?? class_basename($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('handle')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('widget_count')
                    ->label('Widgets')
                    ->getStateUsing(fn (RecordDetailView $record): int => $record->pageWidgets()->count()),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('record_type', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('record_type')
                    ->options(self::getRecordTypeOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (RecordDetailView $record) use ($action) {
                                if (self::isPrimary($record)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Cannot delete primary View')
                                        ->body("The View [{$record->handle}] is the seeded primary for its record type and cannot be deleted.")
                                        ->danger()
                                        ->send();
                                    $action->cancel();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRecordDetailViews::route('/'),
            'create' => Pages\CreateRecordDetailView::route('/create'),
            'edit'   => Pages\EditRecordDetailView::route('/{record}/edit'),
        ];
    }
}
