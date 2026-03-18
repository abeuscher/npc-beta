<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Forms\Components\QuillEditor;
use App\Models\EmailTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'System Emails';

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Content')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Subject line')
                        ->required(),

                    QuillEditor::make('body')
                        ->label('Body')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('tokens_hint')
                        ->label('Available tokens')
                        ->content(fn ($record) => static::tokenHint($record?->handle))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Branding')
                ->description('Used by the default email wrapper. Ignored when a custom HTML template is uploaded.')
                ->columns(2)
                ->schema([
                    Forms\Components\ColorPicker::make('header_color')
                        ->label('Header colour'),

                    Forms\Components\FileUpload::make('header_image_path')
                        ->label('Header image')
                        ->disk('public')
                        ->directory('email-templates')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                        ->nullable(),

                    Forms\Components\TextInput::make('header_text')
                        ->label('Header headline')
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('footer_sender_name')
                        ->label('Sender name')
                        ->nullable()
                        ->helperText('Overrides the site-wide From name for this email.'),

                    Forms\Components\TextInput::make('footer_reply_to')
                        ->label('Reply-to address')
                        ->email()
                        ->nullable(),

                    Forms\Components\Textarea::make('footer_address')
                        ->label('Mailing address')
                        ->nullable()
                        ->helperText('Required by CAN-SPAM. Falls back to site address if blank.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('footer_reason')
                        ->label('"Why you received this" line')
                        ->nullable()
                        ->helperText('Displayed in the footer. Supports tokens.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Custom Template')
                ->description('Upload a custom HTML file for this email. Place {{content}} where the body should appear. When a custom template is uploaded, the Branding section above is ignored.')
                ->collapsed()
                ->schema([
                    Forms\Components\FileUpload::make('custom_template_path')
                        ->label('Upload HTML template')
                        ->disk('public')
                        ->directory('email-templates')
                        ->visibility('public')
                        ->acceptedFileTypes(['text/html'])
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('handle')
                    ->label('Email')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject line')
                    ->limit(60),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit'  => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    private static function tokenHint(?string $handle): string
    {
        $all = '`{{first_name}}` `{{last_name}}` `{{event_title}}` `{{site_name}}`';

        $extra = match ($handle) {
            'event_reminder'           => ' `{{event_date}}` `{{event_location}}`',
            'registration_confirmation' => ' `{{event_location}}`',
            default                    => '',
        };

        return $all . $extra;
    }
}
