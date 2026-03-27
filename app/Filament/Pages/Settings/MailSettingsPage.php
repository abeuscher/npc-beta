<?php

namespace App\Filament\Pages\Settings;

use App\Mail\TestMail;
use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

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
            'mail_driver'             => SiteSetting::get('mail_driver', 'log'),
            'mail_from_name'          => SiteSetting::get('mail_from_name', ''),
            'mail_from_address'       => SiteSetting::get('mail_from_address', ''),
            'mailchimp_server_prefix' => SiteSetting::get('mailchimp_server_prefix', ''),
            'mailchimp_audience_id'   => SiteSetting::get('mailchimp_audience_id', ''),
            'mailchimp_webhook_path'  => SiteSetting::get('mailchimp_webhook_path', 'mailchimp'),
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

                $this->secretKeySection(
                    'resend_api_key',
                    'Resend — API Key',
                    'Starts with re_. Required when Resend is selected as the mail driver.',
                )->visible(fn (Get $get) => $get('mail_driver') === 'resend'),

                Forms\Components\Section::make('MailChimp — Configuration')
                    ->schema([
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
                            ->helperText('The path segment after /webhooks/ — use a random string in production for security.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                $this->secretKeySection(
                    'mailchimp_api_key',
                    'MailChimp — API Key',
                    'Your MailChimp API key.',
                ),

                $this->secretKeySection(
                    'mailchimp_webhook_secret',
                    'MailChimp — Webhook Secret',
                    'Append ?secret=this-value to the webhook URL you register in MailChimp.',
                ),
            ])
            ->statePath('data');
    }

    private function preserveMailSettings(): void
    {
        $data = $this->data;
        foreach ([
            'mail_driver', 'mail_from_name', 'mail_from_address',
            'mailchimp_server_prefix', 'mailchimp_audience_id', 'mailchimp_webhook_path',
        ] as $key) {
            if (isset($data[$key])) {
                SiteSetting::set($key, $data[$key]);
            }
        }
    }

    private function secretKeySection(string $key, string $heading, string $helperText): Forms\Components\Section
    {
        $isSet = filled(SiteSetting::get($key, ''));

        $statusContent = $isSet
            ? new HtmlString('<span class="font-mono tracking-widest text-gray-500">••••</span>')
            : new HtmlString('<span class="text-sm text-gray-400 italic">Not configured</span>');

        return Forms\Components\Section::make($heading)
            ->description($helperText)
            ->schema([
                Forms\Components\Placeholder::make("{$key}_status")
                    ->label('Stored value')
                    ->content($statusContent),

                Forms\Components\Actions::make([
                    $isSet
                        ? $this->changeKeyAction($key, $heading)
                        : $this->setKeyAction($key, $heading),
                ]),
            ]);
    }

    private function setKeyAction(string $key, string $label): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make("set_{$key}")
            ->label('Set Key')
            ->modalHeading("Set {$label}")
            ->modalDescription(
                'This value will be encrypted and stored permanently. ' .
                'Once saved, it cannot be retrieved or displayed by this system — ' .
                'the stored value is concealed from everyone, including administrators. ' .
                'Record it in a secure location such as a password manager before proceeding.'
            )
            ->form([
                Forms\Components\TextInput::make('value')
                    ->label('Key value')
                    ->password()
                    ->extraInputAttributes(['autocomplete' => 'new-password'])
                    ->required(),
            ])
            ->modalSubmitActionLabel('Save Key')
            ->action(function (array $data) use ($key, $label): void {
                $this->preserveMailSettings();

                SiteSetting::set($key, $data['value']);

                $setting = SiteSetting::where('key', $key)->first();
                if ($setting) {
                    ActivityLogger::log($setting, 'key_set', "{$label} was set");
                }

                Notification::make()->title("{$label} saved")->success()->send();

                $this->redirect(static::getUrl());
            });
    }

    private function changeKeyAction(string $key, string $label): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make("change_{$key}")
            ->label('Change Key')
            ->color('danger')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalHeading("Change {$label}")
            ->modalDescription(
                'Changing this key has immediate impact on email sending and integrations. ' .
                'Ensure you have the correct replacement value before proceeding.'
            )
            ->form([
                Forms\Components\TextInput::make('value')
                    ->label('New key value')
                    ->password()
                    ->extraInputAttributes(['autocomplete' => 'new-password'])
                    ->required(),

                Forms\Components\Checkbox::make('confirmed')
                    ->label('I understand this will immediately affect email functionality.')
                    ->rules(['accepted'])
                    ->validationMessages(['accepted' => 'You must confirm before saving.']),
            ])
            ->modalSubmitActionLabel('Rotate Key')
            ->action(function (array $data) use ($key, $label): void {
                $this->preserveMailSettings();

                SiteSetting::set($key, $data['value']);

                $setting = SiteSetting::where('key', $key)->first();
                if ($setting) {
                    ActivityLogger::log($setting, 'key_rotated', "{$label} was changed");
                }

                Notification::make()->title("{$label} updated")->success()->send();

                $this->redirect(static::getUrl());
            });
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

        SiteSetting::set('mail_driver',             $data['mail_driver']);
        SiteSetting::set('mail_from_name',          trim($data['mail_from_name']));
        SiteSetting::set('mail_from_address',       trim($data['mail_from_address']));
        SiteSetting::set('mailchimp_server_prefix', trim($data['mailchimp_server_prefix'] ?? ''));
        SiteSetting::set('mailchimp_audience_id',   trim($data['mailchimp_audience_id'] ?? ''));
        SiteSetting::set('mailchimp_webhook_path',  trim($data['mailchimp_webhook_path'] ?? 'mailchimp'));

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }
}
