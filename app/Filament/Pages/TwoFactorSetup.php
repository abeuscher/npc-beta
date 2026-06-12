<?php

namespace App\Filament\Pages;

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
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
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/**
 * One-time two-factor enrollment (session 359). The enforcement gate sends an
 * un-enrolled admin here: scan the QR (or type the secret) into an authenticator
 * app, save the recovery codes shown once, then confirm with a live code before
 * two_factor_confirmed_at is set and the session is marked passed.
 *
 * @property Form $form
 */
class TwoFactorSetup extends SimplePage
{
    use InteractsWithFormActions;

    protected static string $view = 'filament.pages.two-factor-setup';

    protected static bool $shouldRegisterNavigation = false;

    /** Route name registered in AdminPanelProvider's routes() closure. */
    public const ROUTE = 'filament.admin.pages.two-factor-setup';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public static function getUrl(): string
    {
        return route(self::ROUTE);
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();

        // Already enrolled — nothing to do here.
        if ($user->hasConfirmedTwoFactor()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        // Generate a pending secret + recovery codes once. Re-running would
        // rotate the secret on every render, invalidating an in-progress scan.
        if (empty($user->two_factor_secret)) {
            app(EnableTwoFactorAuthentication::class)($user);
        }

        $this->form->fill();
    }

    public function confirm(): void
    {
        $user = Filament::auth()->user();

        // Throws a ValidationException (bag: confirmTwoFactorAuthentication) on a
        // bad code; surface it on the form field instead.
        try {
            app(ConfirmTwoFactorAuthentication::class)($user, (string) ($this->form->getState()['code'] ?? ''));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('data.code', __('The provided two factor authentication code was invalid.'));

            return;
        }

        EnsureTwoFactorAuthenticated::markPassed();

        Notification::make()
            ->title('Two-factor authentication enabled')
            ->success()
            ->send();

        redirect()->intended(Filament::getUrl());
    }

    public function getSecret(): string
    {
        return Crypt::decrypt(Filament::auth()->user()->two_factor_secret);
    }

    public function getQrCodeSvg(): string
    {
        return Filament::auth()->user()->twoFactorQrCodeSvg();
    }

    /** @return array<int, string> */
    public function getRecoveryCodes(): array
    {
        return Filament::auth()->user()->recoveryCodes();
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
            ->helperText('Enter the 6-digit code from your authenticator app to finish setup.')
            ->required()
            ->autocomplete('one-time-code')
            ->autofocus()
            ->extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*']);
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm & enable')
                ->submit('confirm'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Set up two-factor authentication';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Set up two-factor authentication';
    }
}
