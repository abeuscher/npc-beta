<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class EmailPreviewWizardAction
{
    /**
     * Build a reusable 3-step email preview wizard Action.
     *
     * Step 1 — Confirm: shows the email type name and recipient summary.
     * Step 2 — Preview: renders the first-recipient email in an isolated iframe.
     * Step 3 — Send:   final prompt before the submit button executes the send.
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
