<?php

namespace App\Filament\Pages\Settings;

use App\Mail\TestMail;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class MailSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Mail';

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.settings.mail-settings-page';

    protected static ?string $title = 'Mail Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mail_driver'               => SiteSetting::get('mail_driver', 'log'),
            'mail_from_name'            => SiteSetting::get('mail_from_name', ''),
            'mail_from_address'         => SiteSetting::get('mail_from_address', ''),
            'resend_api_key'            => SiteSetting::get('resend_api_key', ''),
            'mailchimp_api_key'         => SiteSetting::get('mailchimp_api_key', ''),
            'mailchimp_server_prefix'   => SiteSetting::get('mailchimp_server_prefix', ''),
            'mailchimp_audience_id'     => SiteSetting::get('mailchimp_audience_id', ''),
            'mailchimp_webhook_path'    => SiteSetting::get('mailchimp_webhook_path', 'mailchimp'),
            'mailchimp_webhook_secret'  => SiteSetting::get('mailchimp_webhook_secret', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sending')
                    ->schema([
                        Forms\Components\Select::make('mail_driver')
                            ->label('Mail driver')
                            ->required()
                            ->options([
                                'log'    => 'Local — log only (no sending)',
                                'resend' => 'Resend',
                            ])
                            ->helperText('Local writes emails to the Laravel log. No messages are delivered. Switch to Resend to send real email.')
                            ->live(),

                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('From name')
                            ->required(),

                        Forms\Components\TextInput::make('mail_from_address')
                            ->label('From address')
                            ->email()
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Resend')
                    ->schema([
                        Forms\Components\TextInput::make('resend_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->extraInputAttributes(['autocomplete' => 'new-password'])
                            ->nullable()
                            ->helperText('Your Resend API key. Starts with re_.'),
                    ])
                    ->visible(fn (Get $get) => $get('mail_driver') === 'resend'),

                Forms\Components\Section::make('MailChimp')
                    ->schema([
                        Forms\Components\TextInput::make('mailchimp_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->extraInputAttributes(['autocomplete' => 'new-password'])
                            ->nullable(),

                        Forms\Components\TextInput::make('mailchimp_server_prefix')
                            ->label('Server prefix')
                            ->nullable()
                            ->helperText('The data centre suffix from your API key, e.g. us14.'),

                        Forms\Components\TextInput::make('mailchimp_audience_id')
                            ->label('Audience ID')
                            ->nullable()
                            ->helperText('Found under Audience → Settings → Audience name and defaults.'),

                        Forms\Components\TextInput::make('mailchimp_webhook_path')
                            ->label('Webhook path')
                            ->nullable()
                            ->helperText('The path segment after /webhooks/ — use a random string in production for security.'),

                        Forms\Components\TextInput::make('mailchimp_webhook_secret')
                            ->label('Webhook secret')
                            ->password()
                            ->revealable()
                            ->extraInputAttributes(['autocomplete' => 'new-password'])
                            ->nullable()
                            ->helperText('Append ?secret=this-value to the webhook URL you register in MailChimp.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->label('Send to')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $driver = SiteSetting::get('mail_driver', 'log');

                    if ($driver === 'log') {
                        Notification::make()
                            ->title('Mail driver is set to local')
                            ->body('No email will be delivered. Switch to Resend to send real email.')
                            ->warning()
                            ->send();
                        return;
                    }

                    if (empty(config('services.resend.key'))) {
                        Notification::make()
                            ->title('Resend API key is not configured')
                            ->body('Save a valid API key before sending a test email.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Mail::to($data['email'])->send(new TestMail());

                    Notification::make()
                        ->title('Test email sent')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('mail_driver',              $data['mail_driver']);
        SiteSetting::set('mail_from_name',           trim($data['mail_from_name']));
        SiteSetting::set('mail_from_address',        trim($data['mail_from_address']));
        SiteSetting::set('resend_api_key',           trim($data['resend_api_key'] ?? ''));
        SiteSetting::set('mailchimp_api_key',        trim($data['mailchimp_api_key'] ?? ''));
        SiteSetting::set('mailchimp_server_prefix',  trim($data['mailchimp_server_prefix'] ?? ''));
        SiteSetting::set('mailchimp_audience_id',    trim($data['mailchimp_audience_id'] ?? ''));
        SiteSetting::set('mailchimp_webhook_path',   trim($data['mailchimp_webhook_path'] ?? 'mailchimp'));
        SiteSetting::set('mailchimp_webhook_secret', trim($data['mailchimp_webhook_secret'] ?? ''));

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
