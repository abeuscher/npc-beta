<?php

namespace App\Filament\Actions;

use App\Filament\Resources\EmailTemplateResource;
use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class EmailPreviewWizardAction
{
    /**
     * Build a reusable 3-step email preview wizard Action.
     *
     * Step 1 — Confirm: shows the email type name and recipient summary.
     * Step 2 — Preview: renders the first-recipient email in an isolated iframe.
     * Step 3 — Send:   optional test-send button, then the submit button executes the real send.
     *
     * @param  string          $name                Filament action name / HTML id.
     * @param  string          $emailTypeName       Human-readable label, e.g. "Donation Receipt".
     * @param  string|Closure  $recipientSummary    Text describing who will receive the email.
     *                                              A Closure is called lazily when Step 1 renders.
     * @param  Closure         $previewHtmlResolver Called when Step 2 renders; must return a
     *                                              fully-wrapped HTML string for the first recipient.
     * @param  Closure         $sendCallable        Executed on final submit; receives the merged
     *                                              form data array from all steps. Responsible for
     *                                              sending the email(s) and showing any notification.
     * @param  array           $step1ExtraSchema    Additional Filament form components appended to
     *                                              Step 1 (e.g. a role selector for invitations).
     * @param  string          $submitLabel         Label for the final submit button.
     */
    public static function make(
        string $name,
        string $emailTypeName,
        string|Closure $recipientSummary,
        Closure $previewHtmlResolver,
        Closure $sendCallable,
        array $step1ExtraSchema = [],
        string $submitLabel = 'Send Now',
    ): Actions\Action {
        return Actions\Action::make($name)
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalSubmitActionLabel($submitLabel)
            ->steps([
                Step::make('Confirm')
                    ->schema(array_merge([
                        Forms\Components\Placeholder::make('_confirm')
                            ->hiddenLabel()
                            ->content(function () use ($emailTypeName, $recipientSummary): HtmlString {
                                $summary = is_callable($recipientSummary)
                                    ? ($recipientSummary)()
                                    : $recipientSummary;

                                return new HtmlString(
                                    '<p class="text-sm text-gray-700">You are about to send a <strong>'
                                    . e($emailTypeName)
                                    . '</strong> system email.</p>'
                                    . '<p class="text-sm text-gray-700 mt-2">' . $summary . '</p>'
                                );
                            }),
                    ], $step1ExtraSchema)),

                Step::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('_preview_note')
                            ->hiddenLabel()
                            ->content(function (): HtmlString {
                                $url = EmailTemplateResource::getUrl('index');
                                return new HtmlString(
                                    '<p class="text-sm text-gray-500">You can change the appearance of this email in Settings &rsaquo; '
                                    . '<a href="' . e($url) . '" class="text-primary-600 hover:underline">System Emails</a>.'
                                    . '</p>'
                                );
                            }),

                        Forms\Components\Placeholder::make('_preview')
                            ->hiddenLabel()
                            ->content(function () use ($previewHtmlResolver): HtmlString {
                                $html = ($previewHtmlResolver)();

                                return new HtmlString(
                                    '<iframe srcdoc="' . htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" '
                                    . 'style="width:100%;height:520px;border:1px solid #e5e7eb;border-radius:4px;" '
                                    . 'sandbox="">'
                                    . '</iframe>'
                                );
                            }),
                    ]),

                Step::make('Send')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('_sendTest')
                                ->label('Send Test Email to Me')
                                ->outlined()
                                ->color('gray')
                                ->action(function () use ($previewHtmlResolver, $emailTypeName) {
                                    $html       = ($previewHtmlResolver)();
                                    $adminEmail = auth()->user()->email;

                                    Mail::send(
                                        'mail.system-email',
                                        ['html' => $html],
                                        fn ($message) => $message
                                            ->to($adminEmail)
                                            ->subject('[Test] ' . $emailTypeName)
                                    );

                                    Notification::make()
                                        ->title('Test email sent to ' . $adminEmail)
                                        ->success()
                                        ->send();
                                }),
                        ]),

                        Forms\Components\Placeholder::make('_ready')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-700">Preview confirmed. Click <strong>'
                                . e($submitLabel)
                                . '</strong> to dispatch the email.</p>'
                            )),
                    ]),
            ])
            ->action(function (array $data) use ($sendCallable): void {
                ($sendCallable)($data);
            });
    }
}
