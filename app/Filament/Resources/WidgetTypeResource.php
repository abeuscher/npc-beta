<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WidgetTypeResource\Pages;
use App\Models\Collection;
use App\Models\WidgetType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class WidgetTypeResource extends Resource
{
    protected static ?string $model = WidgetType::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Widget Manager';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_widget_type') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user && ! $user->can('delete_widget_type')) {
            return false;
        }

        return ! WidgetType::isPinned($record->handle) && $record->pageWidgets()->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation === 'create') {
                            $set('handle', Str::slug($state, '_'));
                        }
                    }),

                Forms\Components\TextInput::make('handle')
                    ->required()
                    ->maxLength(255)
                    ->unique(WidgetType::class, 'handle', ignoreRecord: true)
                    ->rules(['alpha_dash'])
                    ->helperText('Machine identifier. Auto-generated from label on create. Cannot be changed after creation.')
                    ->disabled(fn (string $operation) => $operation === 'edit'),

                Forms\Components\Select::make('render_mode')
                    ->required()
                    ->options([
                        'server' => 'Server',
                        'client' => 'Client',
                    ])
                    ->live()
                    ->columnSpanFull()
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set, Forms\Get $get) {
                        if ($operation === 'create' && $state === 'server' && blank($get('template'))) {
                            $set('template', self::defaultTemplate());
                        }
                    }),

                Forms\Components\Select::make('collections')
                    ->multiple()
                    ->options(fn () => Collection::public()->pluck('name', 'handle')->all())
                    ->helperText('Collection handles this widget declares as data sources.')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('default_open')
                    ->label('Start open in page builder')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Singleton Fields')
                ->description('Fields the editor fills in per block instance. Values are stored in page_widgets.config.')
                ->schema([
                    Forms\Components\Repeater::make('config_schema')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->rules(['alpha_dash'])
                                ->helperText('Lowercase with underscores, e.g. heading_text'),

                            Forms\Components\TextInput::make('label')
                                ->required(),

                            Forms\Components\Select::make('type')
                                ->required()
                                ->options([
                                    'text'      => 'Text',
                                    'textarea'  => 'Textarea',
                                    'richtext'  => 'Rich Text',
                                    'url'       => 'URL',
                                    'number'    => 'Number',
                                    'toggle'    => 'Toggle',
                                ])
                                ->default('text'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                ]),

            Forms\Components\Section::make('Server Mode')
                ->schema([
                    Forms\Components\Textarea::make('template')
                        ->label('Blade Template')
                        ->rows(10)
                        ->default(fn () => self::defaultTemplate())
                        ->helperText('Variables available: collection handles declared above, e.g. $blog_posts.')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('js')
                        ->label('JavaScript')
                        ->rows(6)
                        ->default('// JavaScript runs after the page loads. Alpine.js is already available globally — no import needed.')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Forms\Get $get) => $get('render_mode') === 'server'),

            Forms\Components\Section::make('Client Mode')
                ->schema([
                    Forms\Components\TextInput::make('variable_name')
                        ->label('JS Variable Name')
                        ->rules(['alpha_dash'])
                        ->helperText('Window variable name for injected JSON data (e.g. boardMembers → window.boardMembers).'),

                    Forms\Components\Textarea::make('code')
                        ->label('Code')
                        ->rows(10)
                        ->helperText('Warning: This field executes arbitrary code in the browser. Use only code you trust.')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Forms\Get $get) => $get('render_mode') === 'client'),

            Forms\Components\Section::make('CSS')
                ->schema([
                    Forms\Components\Textarea::make('css')
                        ->label('CSS')
                        ->rows(6)
                        ->default('/* Styles are scoped to this widget via .widget--{handle}. */')
                        ->helperText('Inlined in a <style> tag when this widget appears on a page.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('handle')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('render_mode')
                    ->label('Mode')
                    ->colors([
                        'primary' => 'server',
                        'warning' => 'client',
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('collections_count')
                    ->label('Collections')
                    ->getStateUsing(fn (WidgetType $record) => count($record->collections ?? []))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('label')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (WidgetType $record): bool => ! auth()->user()?->can('delete_widget_type') || WidgetType::isPinned($record->handle) || $record->pageWidgets()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn () => ! auth()->user()?->can('delete_widget_type'))
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (WidgetType $record) {
                                if (! WidgetType::isPinned($record->handle) && $record->pageWidgets()->doesntExist()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    private static function defaultTemplate(): string
    {
        return <<<'BLADE'
<div x-data="{ detail: false }">
    <h2>Widget Title</h2>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>

    {{-- Alpine.js is active on this page. Remove this block when you no longer need it. --}}
    <button type="button" x-on:click="detail = !detail">
        <span x-text="detail ? 'Hide' : 'Show'">Show</span> detail
    </button>
    <p x-show="detail" x-cloak>
        Alpine.js is wired up. You can use x-data, x-show, x-on, x-bind, and all other Alpine directives here.
    </p>
</div>
BLADE;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWidgetTypes::route('/'),
            'create' => Pages\CreateWidgetType::route('/create'),
            'edit'   => Pages\EditWidgetType::route('/{record}/edit'),
        ];
    }
}
