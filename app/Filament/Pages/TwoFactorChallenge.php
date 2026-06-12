<?php

namespace App\Filament\Pages;

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Login challenge (session 359). An enrolled admin who hasn't yet cleared the
 * second factor this session is sent here after the password login. Accepts a
 * TOTP code from the authenticator app or one single-use recovery code; on
 * success the session is marked passed and the user lands on their destination.
 *
 * @property Form $form
 */
class TwoFactorChallenge extends SimplePage
{
    use InteractsWithFormActions;
    use WithRateLimiting;

    protected static string $view = 'filament.pages.two-factor-challenge';

    protected static bool $shouldRegisterNavigation = false;

    /** Route name registered in AdminPanelProvider's routes() closure. */
    public const ROUTE = 'filament.admin.pages.two-factor-challenge';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public static function getUrl(): string
    {
        return route(self::ROUTE);
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();

        // Not enrolled yet — enrollment comes before the challenge.
        if (! $user->hasConfirmedTwoFactor()) {
            redirect()->intended(TwoFactorSetup::getUrl());

            return;
        }

        // Already cleared this session — nothing to challenge.
        if (session(EnsureTwoFactorAuthenticated::SESSION_KEY)) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function authenticate(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many attempts')
                ->body("Please wait {$exception->secondsUntilAvailable} seconds before trying again.")
                ->danger()
                ->send();

            return;
        }

        $code = trim((string) ($this->form->getState()['code'] ?? ''));

        if ($code === '' || ! $this->verifyCode($code)) {
            $this->addError('data.code', __('The provided two factor authentication code was invalid.'));

            return;
        }

        EnsureTwoFactorAuthenticated::markPassed();

        redirect()->intended(Filament::getUrl());
    }

    /**
     * Verify a TOTP code or consume a single-use recovery code. Recovery codes
     * are replaced on use, so a given code never works twice.
     */
    protected function verifyCode(string $code): bool
    {
        $user = Filament::auth()->user();

        if (app(TwoFactorAuthenticationProvider::class)->verify(Crypt::decrypt($user->two_factor_secret), $code)) {
            return true;
        }

        if (in_array($code, $user->recoveryCodes(), true)) {
            $user->replaceRecoveryCode($code);

            return true;
        }

        return false;
    }

    /** @return array<int | string, string | Form> */
    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([$this->getCodeFormComponent()])
                ->statePath('data'),
        ];
    }

    protected function getCodeFormComponent(): Component
    {
        return TextInput::make('code')
            ->label('Authentication code')
            ->helperText('Enter the code from your authenticator app, or one of your recovery codes.')
            ->required()
            ->autocomplete('one-time-code')
            ->autofocus()
            ->extraInputAttributes(['inputmode' => 'text']);
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('authenticate')
                ->label('Verify')
                ->submit('authenticate'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Two-factor authentication';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Two-factor authentication';
    }
}
