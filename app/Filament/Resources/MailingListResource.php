<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailingListResource\Pages;
use App\Models\MailingList;
use App\Services\MailingListFieldRegistry;
use App\Services\MailingListQueryBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MailingListResource extends Resource
{
    protected static ?string $model = MailingList::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Mailing Lists';

    protected static ?int $navigationSort = 6;

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_mailing_list') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('List')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required(),

                    Forms\Components\Select::make('conjunction')
                        ->label('Match')
                        ->options([
                            'and' => 'ALL rules must match (AND)',
                            'or'  => 'ANY rule must match (OR)',
                        ])
                        ->required()
                        ->default('and')
                        ->hidden(fn (\Filament\Forms\Get $get) => filled($get('raw_where'))),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Simple Filters')
                ->schema([
                    Forms\Components\Repeater::make('filters')
                        ->relationship('filters')
                        ->reorderable('sort_order')
                        ->schema([
                            Forms\Components\Select::make('field')
                                ->label('Field')
                                ->options(MailingListFieldRegistry::fields())
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('operator', null)),

                            Forms\Components\Select::make('operator')
                                ->label('Operator')
                                ->options(fn (Forms\Get $get) => MailingListFieldRegistry::operatorsFor($get('field')))
                                ->required()
                                ->live(),

                            // Text value — visible for text fields when operator requires a value
                            Forms\Components\TextInput::make('value')
                                ->label('Value')
                                ->nullable()
                                ->visible(fn (Forms\Get $get): bool =>
                                    MailingListFieldRegistry::valueTypeFor($get('field')) === 'text'
                                    && ! in_array($get('operator'), ['is_empty', 'is_not_empty'], true)
                                ),

                            // Boolean select — visible for mailing_list_opt_in
                            Forms\Components\Select::make('value')
                                ->label('Value')
                                ->options(['1' => 'Yes', '0' => 'No'])
                                ->nullable()
                                ->visible(fn (Forms\Get $get): bool =>
                                    MailingListFieldRegistry::valueTypeFor($get('field')) === 'select'
                                    && ! in_array($get('operator'), ['is_empty', 'is_not_empty'], true)
                                ),

                            // Tag picker — visible for tags field
                            Forms\Components\Select::make('value')
                                ->label('Tag')
                                ->options(fn () => MailingListFieldRegistry::tagOptions())
                                ->searchable()
                                ->nullable()
                                ->visible(fn (Forms\Get $get): bool =>
                                    MailingListFieldRegistry::valueTypeFor($get('field')) === 'tag_picker'
                                ),
                        ])
                        ->columns(3)
                        ->defaultItems(0),

                    Forms\Components\Placeholder::make('contact_count')
                        ->label('Matching contacts')
                        ->content(function (?MailingList $record): string {
                            if (! $record) {
                                return '—';
                            }
                            try {
                                $count = $record->contacts()->count();
                                return "{$count} contact(s) match this list's filters.";
                            } catch (\Exception $e) {
                                return 'Unable to evaluate filters: ' . $e->getMessage();
                            }
                        }),
                ])
                ->hidden(fn (\Filament\Forms\Get $get) => filled($get('raw_where'))),

            Forms\Components\Section::make('Advanced Filter')
                ->schema([
                    Forms\Components\Placeholder::make('advanced_warning')
                        ->hiddenLabel()
                        ->content('⚠ Advanced mode. Write a raw PostgreSQL WHERE clause applied to the contacts table. Simple Filters above are ignored when a WHERE clause is provided. Queries time out after 5 seconds. See the help article for the table schema and examples.'),

                    Forms\Components\Textarea::make('raw_where')
                        ->label('WHERE clause')
                        ->nullable()
                        ->extraAttributes(['style' => 'font-family: monospace'])
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('advanced_contact_count')
                        ->label('Matching contacts')
                        ->content(function (?MailingList $record): string {
                            if (! $record || ! $record->raw_where) {
                                return '—';
                            }
                            try {
                                $count = $record->contacts()->count();
                                return "{$count} contact(s) match this WHERE clause.";
                            } catch (\Exception $e) {
                                return 'Unable to evaluate clause: ' . $e->getMessage();
                            }
                        }),
                ])
                ->collapsible()
                ->collapsed(fn (?MailingList $record) => ! ($record?->raw_where))
                ->visible(fn () => auth()->user()?->can('use_advanced_list_filters')),

        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('List')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('conjunction')
                    ->label('Conjunction')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'and' ? 'ALL rules' : 'ANY rule')
                    ->color(fn (string $state) => $state === 'and' ? 'info' : 'warning'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('contact_count')
                    ->label('Contacts')
                    ->getStateUsing(function (MailingList $record): string {
                        try {
                            return (string) $record->contacts()->count();
                        } catch (\Exception) {
                            return '—';
                        }
                    }),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMailingLists::route('/'),
            'create' => Pages\CreateMailingList::route('/create'),
            'edit'   => Pages\EditMailingList::route('/{record}/edit'),
        ];
    }

    // ── CSV export ─────────────────────────────────────────────────────────────

    public static function streamCsvExport(MailingList $record): StreamedResponse
    {
        $filename = 'list-' . str($record->name)->slug() . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($record) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['first_name', 'last_name', 'email', 'phone', 'city', 'state', 'postal_code', 'tags']);

            $record->contacts()
                ->with('tags')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->each(function ($contact) use ($handle) {
                    fputcsv($handle, [
                        $contact->first_name,
                        $contact->last_name,
                        $contact->email,
                        $contact->phone,
                        $contact->city,
                        $contact->state,
                        $contact->postal_code,
                        $contact->tags->pluck('name')->implode(', '),
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
