<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormResource\Pages;
use App\Filament\Resources\FormResource\RelationManagers\FormSubmissionsRelationManager;
use App\Models\CustomFieldDef;
use App\Models\Form;
use Filament\Forms;
use Filament\Forms\Form as FilamentForm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Forms';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_form') ?? false;
    }

    public static function form(FilamentForm $form): FilamentForm
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                        if (! $get('handle') || $get('handle') === Str::slug($get('title'), '_')) {
                            $set('handle', Str::slug($state ?? '', '_'));
                        }
                    }),

                Forms\Components\TextInput::make('handle')
                    ->required()
                    ->maxLength(255)
                    ->unique(Form::class, 'handle', ignoreRecord: true)
                    ->rules(['regex:/^[a-z0-9_\-]+$/'])
                    ->helperText('Lowercase letters, numbers, hyphens, underscores. Used in <x-public-form handle="…">.'),

                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Admin notes only — not shown publicly.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Form Settings')->schema([
                Forms\Components\Select::make('settings.form_type')
                    ->label('Form type')
                    ->options([
                        'general' => 'General',
                        'contact' => 'Contact sign-up',
                    ])
                    ->default('general')
                    ->live()
                    ->helperText(fn (Forms\Get $get) => $get('settings.form_type') === 'contact'
                        ? 'Contact forms should map first_name, last_name, and email fields to contact record columns.'
                        : null),

                Forms\Components\TextInput::make('settings.submit_label')
                    ->label('Submit button label')
                    ->default('Submit')
                    ->required(),

                Forms\Components\Textarea::make('settings.success_message')
                    ->label('Success message')
                    ->default('Thank you. Your message has been received.')
                    ->rows(2)
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('settings.honeypot')
                    ->label('Enable honeypot spam protection')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Fields')
                ->description('Define the fields that appear on the public form.')
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->label('')
                        ->schema(static::fieldRepeaterSchema())
                        ->defaultItems(0)
                        ->addActionLabel('Add field')
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function contactFieldOptions(): array
    {
        $standard = [
            'first_name'          => 'first_name',
            'last_name'           => 'last_name',
            'email'               => 'email',
            'phone'               => 'phone',
            'address_line_1'      => 'address_line_1',
            'address_line_2'      => 'address_line_2',
            'city'                => 'city',
            'state'               => 'state',
            'postal_code'         => 'postal_code',
            'country'             => 'country',
            'mailing_list_opt_in' => 'mailing_list_opt_in',
        ];

        $custom = CustomFieldDef::forModel('contact')
            ->get()
            ->mapWithKeys(fn ($def) => ["custom_fields.{$def->handle}" => "custom: {$def->handle}"])
            ->toArray();

        return array_merge(['' => '— None —'], $standard, $custom);
    }

    private static function fieldRepeaterSchema(): array
    {
        $typeOptions = [
            'text'     => 'Text',
            'email'    => 'Email',
            'tel'      => 'Phone',
            'number'   => 'Number',
            'textarea' => 'Textarea',
            'select'   => 'Select (dropdown)',
            'radio'    => 'Radio buttons',
            'checkbox' => 'Checkbox',
            'state'    => 'US State',
            'country'  => 'Country',
            'hidden'   => 'Hidden',
        ];

        $validationOptions = [
            'none'         => 'None',
            'email'        => 'Email address',
            'phone'        => 'Phone number',
            'zip'          => 'ZIP code',
            'url'          => 'URL',
            'numbers_only' => 'Numbers only',
            'letters_only' => 'Letters only',
            'custom_regex' => 'Custom regex',
        ];

        $widthOptions = collect(range(1, 12))->mapWithKeys(fn ($i) => [$i => (string) $i])->toArray();

        $noPlaceholderTypes  = ['checkbox', 'radio', 'select', 'hidden', 'state', 'country'];
        $hasOptionsTypes     = ['select', 'radio'];

        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options($typeOptions)
                    ->default('text')
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                        if (! $get('handle')) {
                            $set('handle', Str::slug($state ?? '', '_'));
                        }
                    }),

                Forms\Components\TextInput::make('handle')
                    ->label('Handle')
                    ->required()
                    ->rules(['regex:/^[a-z0-9_]+$/'])
                    ->helperText('Lowercase letters, numbers, underscores.'),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('placeholder')
                    ->label('Placeholder')
                    ->hidden(fn (Forms\Get $get) => in_array($get('type'), $noPlaceholderTypes)),

                Forms\Components\TextInput::make('default_value')
                    ->label('Default value')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'hidden'),

                Forms\Components\Select::make('width')
                    ->label('Width (columns)')
                    ->options($widthOptions)
                    ->default(12)
                    ->required(),

                Forms\Components\Toggle::make('required')
                    ->label('Required')
                    ->default(false)
                    ->hidden(fn (Forms\Get $get) => $get('type') === 'hidden'),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('validation')
                    ->label('Validation')
                    ->options($validationOptions)
                    ->default('none')
                    ->live(),

                Forms\Components\Select::make('contact_field')
                    ->label('Maps to contact field')
                    ->options(static::contactFieldOptions())
                    ->default('')
                    ->helperText('Optional. Links this field to a contact record column for session 048 contact creation.'),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('validation_regex')
                    ->label('Regex pattern')
                    ->visible(fn (Forms\Get $get) => $get('validation') === 'custom_regex'),

                Forms\Components\TextInput::make('validation_message')
                    ->label('Custom error message')
                    ->visible(fn (Forms\Get $get) => $get('validation') === 'custom_regex'),
            ]),

            Forms\Components\Repeater::make('options')
                ->label('Options')
                ->schema([
                    Forms\Components\TextInput::make('value')->required(),
                    Forms\Components\TextInput::make('label')->required(),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add option')
                ->columns(2)
                ->visible(fn (Forms\Get $get) => in_array($get('type'), $hasOptionsTypes)),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('handle')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('submissions_count')
                    ->label('Submissions')
                    ->counts('submissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('download_json')
                    ->label('Download JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Form $record) {
                        $json = json_encode([
                            'title'    => $record->title,
                            'handle'   => $record->handle,
                            'fields'   => $record->fields,
                            'settings' => $record->settings,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                        return response()->streamDownload(
                            fn () => print($json),
                            Str::slug($record->handle) . '-form.json',
                            ['Content-Type' => 'application/json']
                        );
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FormSubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit'   => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}
