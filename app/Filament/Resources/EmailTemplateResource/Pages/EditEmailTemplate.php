<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

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

                    $template = $this->record;
                    $tokens   = $this->dummyTokens();
                    $body     = $template->render($tokens);
                    $html     = $template->resolveWrapper($body);
                    $subject  = '[TEST] ' . $template->renderSubject($tokens);

                    Mail::html($html, function ($message) use ($data, $subject) {
                        $message->to($data['email'])->subject($subject);
                    });

                    Notification::make()
                        ->title('Test email sent')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function dummyTokens(): array
    {
        return [
            'first_name'     => 'Fred',
            'last_name'      => 'Flintstone',
            'event_title'    => 'Annual Charity Gala',
            'event_date'     => 'Saturday, April 5, 2026',
            'event_location' => '123 Bedrock Lane, Bedrock',
            'site_name'      => SiteSetting::get('site_name', ''),
        ];
    }
}
