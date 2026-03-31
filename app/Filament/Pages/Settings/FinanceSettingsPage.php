<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Services\QuickBooks\QuickBooksAuth;
use App\Services\QuickBooks\QuickBooksClient;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Payment method types supported by Stripe Checkout.
     * Maps internal key to human-readable label.
     */
    public const PAYMENT_METHOD_OPTIONS = [
        'card'            => 'Card (Visa, Mastercard, Amex, etc.)',
        'us_bank_account' => 'ACH Direct Debit (US bank account)',
        'link'            => 'Link (Stripe\'s one-click checkout)',
        'cashapp'         => 'Cash App Pay',
        'amazon_pay'      => 'Amazon Pay',
    ];

    /**
     * Payment method types that Stripe supports for subscription (recurring) mode.
     */
    public const SUBSCRIPTION_COMPATIBLE_METHODS = ['card', 'us_bank_account', 'link'];

    public function mount(): void
    {
        $this->form->fill([
            'stripe_publishable_key'      => SiteSetting::get('stripe_publishable_key', ''),
            'stripe_payment_method_types' => SiteSetting::get('stripe_payment_method_types') ?? ['card'],
            'qb_income_account_id'        => SiteSetting::get('qb_income_account_id', ''),
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

                Forms\Components\Section::make('Payment Methods')
                    ->description('Choose which payment methods are available at checkout. Card payments cannot be disabled.')
                    ->schema([
                        Forms\Components\CheckboxList::make('stripe_payment_method_types')
                            ->label('')
                            ->options(self::PAYMENT_METHOD_OPTIONS)
                            ->descriptions([
                                'us_bank_account' => 'Supports recurring donations.',
                                'link'            => 'Supports recurring donations.',
                                'cashapp'         => 'One-time payments only.',
                                'amazon_pay'      => 'One-time payments only.',
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value === 'card')
                            ->default(['card'])
                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, $state) {
                                $values = is_array($state) ? $state : ['card'];
                                if (! in_array('card', $values)) {
                                    $values[] = 'card';
                                }
                                $component->state($values);
                            })
                            ->helperText('Card payments are always enabled. Methods marked "one-time only" are automatically excluded from recurring donation checkout.'),
                    ]),

                ...$this->quickBooksSection(),
            ])
            ->statePath('data');
    }

    private function preservePublishableKey(): void
    {
        $value = $this->data['stripe_publishable_key'] ?? null;
        if (filled($value)) {
            SiteSetting::set('stripe_publishable_key', $value);
        }

        $methods = $this->data['stripe_payment_method_types'] ?? null;
        if (is_array($methods)) {
            $this->savePaymentMethodTypes($methods);
        }

        $incomeAccount = $this->data['qb_income_account_id'] ?? null;
        if ($incomeAccount !== null) {
            $this->saveIncomeAccountSetting($incomeAccount);
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

    private function quickBooksSection(): array
    {
        $auth = app(QuickBooksAuth::class);
        $clientIdConfigured = filled(SiteSetting::get('qb_client_id', ''));
        $clientSecretConfigured = filled(SiteSetting::get('qb_client_secret', ''));
        $realmId = $auth->getRealmId();
        $expiresAt = $auth->getTokenExpiresAt();
        $isConnected = filled($realmId);

        $sections = [
            $this->secretKeySection(
                'qb_client_id',
                'QuickBooks — Client ID',
                'OAuth Client ID from the Intuit Developer Portal. Found under your app\'s Keys & credentials tab.',
            ),

            $this->secretKeySection(
                'qb_client_secret',
                'QuickBooks — Client Secret',
                'OAuth Client Secret from the Intuit Developer Portal. Found under your app\'s Keys & credentials tab.',
            ),
        ];

        if ($isConnected) {
            $expiresFormatted = $expiresAt
                ? Carbon::parse($expiresAt)->format('M j, Y g:i A T')
                : 'Unknown';

            $sections[] = Forms\Components\Section::make('QuickBooks — Connection')
                ->schema([
                    Forms\Components\Placeholder::make('qb_status')
                        ->label('')
                        ->content(new HtmlString(
                            '<div class="flex items-center gap-2 mb-3">'
                            . '<span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">Connected</span>'
                            . '</div>'
                            . '<dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">'
                            . '<div><dt class="text-gray-500 dark:text-gray-400">Company ID (Realm)</dt><dd class="font-mono">' . e($realmId) . '</dd></div>'
                            . '<div><dt class="text-gray-500 dark:text-gray-400">Token expires</dt><dd>' . e($expiresFormatted) . '</dd></div>'
                            . '</dl>'
                        )),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('qb_disconnect')
                            ->label('Disconnect')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Disconnect QuickBooks')
                            ->modalDescription('This will remove the stored QuickBooks connection. You will need to re-authorize to reconnect.')
                            ->action(function (): void {
                                $qbAuth = app(QuickBooksAuth::class);
                                $currentRealmId = $qbAuth->getRealmId();
                                $setting = SiteSetting::where('key', 'qb_realm_id')->first();

                                $qbAuth->disconnect();

                                if ($setting) {
                                    ActivityLogger::log($setting, 'quickbooks_disconnected', "QuickBooks disconnected (Realm ID: {$currentRealmId})");
                                }

                                Notification::make()->title('QuickBooks disconnected')->success()->send();

                                $this->redirect(static::getUrl());
                            }),
                    ]),
                ]);

            $sections[] = $this->quickBooksSyncSection();
        } elseif ($clientIdConfigured && $clientSecretConfigured) {
            $sections[] = Forms\Components\Section::make('QuickBooks — Connection')
                ->schema([
                    Forms\Components\Placeholder::make('qb_not_connected')
                        ->label('')
                        ->content(new HtmlString(
                            '<p class="text-sm text-gray-500 dark:text-gray-400 mb-3">'
                            . 'Client ID and Secret are configured. Click below to authorize with QuickBooks Online.'
                            . '</p>'
                        )),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('qb_connect')
                            ->label('Connect to QuickBooks')
                            ->url(route('filament.admin.quickbooks.connect'))
                            ->icon('heroicon-o-arrow-top-right-on-square'),
                    ]),
                ]);
        }

        return $sections;
    }

    private function quickBooksSyncSection(): Forms\Components\Section
    {
        $accounts = Cache::remember('qb_income_accounts', 3600, function () {
            try {
                return app(QuickBooksClient::class)->getIncomeAccounts();
            } catch (\Throwable) {
                return [];
            }
        });

        return Forms\Components\Section::make('QuickBooks — Transaction Sync')
            ->description('All synced transactions will be posted to the selected income account in QuickBooks.')
            ->schema([
                Forms\Components\Select::make('qb_income_account_id')
                    ->label('Income Account')
                    ->options($accounts)
                    ->placeholder($accounts ? 'Select an income account' : 'No accounts available — try refreshing')
                    ->helperText('Completed payments and refunds are automatically synced to this account. Sync is disabled until an account is selected.'),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('qb_refresh_accounts')
                        ->label('Refresh Accounts')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->action(function (): void {
                            Cache::forget('qb_income_accounts');

                            Notification::make()->title('Account list refreshed')->success()->send();

                            $this->redirect(static::getUrl());
                        }),
                ]),
            ]);
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
        $this->savePaymentMethodTypes($data['stripe_payment_method_types'] ?? ['card']);

        $this->saveIncomeAccountSetting($data['qb_income_account_id'] ?? '');

        Artisan::call('config:clear');

        Notification::make()->title('Settings saved')->success()->send();

        $this->redirect(static::getUrl());
    }

    private function savePaymentMethodTypes(array $types): void
    {
        // Ensure 'card' is always present
        if (! in_array('card', $types)) {
            $types[] = 'card';
        }

        $setting = SiteSetting::where('key', 'stripe_payment_method_types')->first();
        if ($setting) {
            $setting->update(['value' => json_encode(array_values($types))]);
        } else {
            SiteSetting::create([
                'key'   => 'stripe_payment_method_types',
                'value' => json_encode(array_values($types)),
                'group' => 'finance',
                'type'  => 'json',
            ]);
        }
        Cache::forget('site_setting:stripe_payment_method_types');
    }

    private function saveIncomeAccountSetting(string $value): void
    {
        $setting = SiteSetting::where('key', 'qb_income_account_id')->first();
        if ($setting) {
            $setting->update(['value' => $value ?: null]);
        } else {
            SiteSetting::create([
                'key'   => 'qb_income_account_id',
                'value' => $value ?: null,
                'group' => 'finance',
                'type'  => 'string',
            ]);
        }
        Cache::forget('site_setting:qb_income_account_id');
    }
}
