<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

class FinanceSettingsPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        return $user->hasRole('super_admin') || $user->can('manage_financial_settings');
    }

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.settings.finance-settings-page';

    protected static ?string $title = 'Finance Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'stripe_publishable_key' => SiteSetting::get('stripe_publishable_key', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stripe — Public Key')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_publishable_key')
                            ->label('Publishable Key')
                            ->extraInputAttributes(['autocomplete' => 'new-password'])
                            ->nullable()
                            ->helperText('Starts with pk_live_ or pk_test_. This is a public key — safe to display and store in plaintext.')
                            ->columnSpanFull(),
                    ]),

                $this->secretKeySection(
                    'stripe_secret_key',
                    'Stripe — Secret Key',
                    'Starts with rk_ (restricted) or sk_live_. Use a restricted key scoped to the minimum required permissions.',
                ),

                $this->secretKeySection(
                    'stripe_webhook_secret',
                    'Stripe — Webhook Secret',
                    'Starts with whsec_. Generated in the Stripe dashboard under Webhooks.',
                ),
            ])
            ->statePath('data');
    }

    private function preservePublishableKey(): void
    {
        $value = $this->data['stripe_publishable_key'] ?? null;
        if (filled($value)) {
            SiteSetting::set('stripe_publishable_key', $value);
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
                $this->preservePublishableKey();

                SiteSetting::set($key, $data['value']);

                $setting = SiteSetting::where('key', $key)->first();
                if ($setting) {
                    ActivityLogger::log($setting, 'key_set', "{$label} was set");
                }

                Artisan::call('config:clear');

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
                'Changing a financial API key has immediate and consequential impact on this system. ' .
                'Payment processing, webhook verification, or third-party sync may break if the wrong value is entered. ' .
                'Ensure you have taken a full system backup and know exactly what you are doing before proceeding.'
            )
            ->form([
                Forms\Components\TextInput::make('value')
                    ->label('New key value')
                    ->password()
                    ->extraInputAttributes(['autocomplete' => 'new-password'])
                    ->required(),

                Forms\Components\Checkbox::make('confirmed')
                    ->label('I have taken a system backup and I understand the consequences of rotating this key.')
                    ->rules(['accepted'])
                    ->validationMessages(['accepted' => 'You must confirm before saving.']),
            ])
            ->modalSubmitActionLabel('Rotate Key')
            ->action(function (array $data) use ($key, $label): void {
                $this->preservePublishableKey();

                SiteSetting::set($key, $data['value']);

                $setting = SiteSetting::where('key', $key)->first();
                if ($setting) {
                    ActivityLogger::log($setting, 'key_rotated', "{$label} was changed");
                }

                Artisan::call('config:clear');

                Notification::make()->title("{$label} updated")->success()->send();

                $this->redirect(static::getUrl());
            });
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('stripe_publishable_key', $data['stripe_publishable_key'] ?? '');

        Artisan::call('config:clear');

        Notification::make()->title('Settings saved')->success()->send();

        $this->redirect(static::getUrl());
    }
}
