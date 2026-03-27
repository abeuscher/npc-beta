<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Actions\EmailPreviewWizardAction;
use App\Filament\Resources\EventResource;
use App\Mail\EventCancellation;
use App\Mail\EventReminder;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createLandingPage')
                ->label('Create basic landing page')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn () => $this->getRecord()->landing_page_id === null)
                ->requiresConfirmation()
                ->modalHeading('Create landing page')
                ->modalDescription('This will create a new draft page with event widgets pre-configured. You can edit it fully after creation.')
                ->action(function () {
                    $event = $this->getRecord();

                    EventResource::createLandingPageForEvent($event);

                    \Filament\Notifications\Notification::make()
                        ->title('Landing page created')
                        ->body('The page is saved as a draft. Edit it to customise before publishing.')
                        ->success()
                        ->send();

                    $this->redirect(
                        \App\Filament\Resources\PageResource::getUrl('edit', ['record' => $event->fresh()->landingPage])
                    );
                }),

            Actions\Action::make('exportRegistrants')
                ->label('Export registrants')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->registrations()->exists())
                ->action(function (): StreamedResponse {
                    $event = $this->getRecord();
                    $filename = 'registrants-' . $event->slug . '-' . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () use ($event) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            'name', 'email', 'phone', 'company',
                            'address_line_1', 'city', 'state', 'zip',
                            'status', 'registered_at',
                        ]);

                        $event->registrations()->orderBy('registered_at')->each(function ($reg) use ($handle) {
                            fputcsv($handle, [
                                $reg->name,
                                $reg->email,
                                $reg->phone,
                                $reg->company,
                                $reg->address_line_1,
                                $reg->city,
                                $reg->state,
                                $reg->zip,
                                $reg->status,
                                $reg->registered_at?->toDateTimeString(),
                            ]);
                        });

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),

            Actions\Action::make('sendRemindersNow')
                ->label('Send reminders now')
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send reminder emails')
                ->modalDescription('This will immediately email all current registrants who have an email address. Continue?')
                ->visible(function () {
                    $event       = $this->getRecord();
                    $hasUpcoming = $event->starts_at && $event->starts_at >= now();
                    $hasEmails   = $event->registrations()->where('status', 'registered')->whereNotNull('email')->where('email', '!=', '')->exists();
                    return $hasUpcoming && $hasEmails;
                })
                ->action(function () {
                    $event = $this->getRecord();

                    $registrations = $event->registrations()
                        ->where('status', 'registered')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->get();

                    $sent = 0;

                    foreach ($registrations as $registration) {
                        Mail::to($registration->email)->send(new EventReminder($registration, $event));
                        $sent++;
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Reminders sent')
                        ->body("Sent {$sent} reminder " . ($sent === 1 ? 'email' : 'emails') . '.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('deleteRegistrantContacts')
                ->label('Delete registrant contacts')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->visible(function () {
                    $event = $this->getRecord();

                    if ($event->status === 'cancelled') {
                        return true;
                    }

                    return $event->starts_at && $event->starts_at < now();
                })
                ->requiresConfirmation()
                ->modalHeading('Delete registrant contacts')
                ->modalDescription(function () {
                    $event   = $this->getRecord();
                    $eventId = $event->id;

                    $linkedIds = DB::table('event_registrations')
                        ->where('event_id', $eventId)
                        ->whereNotNull('contact_id')
                        ->pluck('contact_id');

                    $contactCount = $linkedIds->isNotEmpty()
                        ? Contact::whereIn('id', $linkedIds)
                            ->where('source', 'web_form')
                            ->whereNotExists(function ($q) use ($eventId) {
                                $q->select(DB::raw(1))
                                    ->from('event_registrations')
                                    ->whereColumn('event_registrations.contact_id', 'contacts.id')
                                    ->where('event_id', '!=', $eventId);
                            })
                            ->whereNotExists(function ($q) {
                                $q->select(DB::raw(1))
                                    ->from('memberships')
                                    ->whereColumn('memberships.contact_id', 'contacts.id');
                            })
                            ->whereNotExists(function ($q) {
                                $q->select(DB::raw(1))
                                    ->from('donations')
                                    ->whereColumn('donations.contact_id', 'contacts.id');
                            })
                            ->count()
                        : 0;

                    $registrationCount = DB::table('event_registrations')
                        ->where('event_id', $eventId)
                        ->count();

                    $contactWord = $contactCount === 1 ? 'contact' : 'contacts';
                    $regWord     = $registrationCount === 1 ? 'record' : 'records';

                    return "{$contactCount} {$contactWord} will be deleted and {$registrationCount} registration {$regWord} will be removed. "
                        . 'This will permanently remove these contacts and all registration records for this event. '
                        . 'Contacts with memberships, donations, or registrations at other events are not affected. '
                        . 'This operation may take a moment to complete.';
                })
                ->modalSubmitActionLabel('Delete')
                ->action(function () {
                    $event   = $this->getRecord();
                    $eventId = $event->id;

                    $linkedIds = DB::table('event_registrations')
                        ->where('event_id', $eventId)
                        ->whereNotNull('contact_id')
                        ->pluck('contact_id');

                    $contactCount = 0;

                    if ($linkedIds->isNotEmpty()) {
                        $eligible = Contact::whereIn('id', $linkedIds)
                            ->where('source', 'web_form')
                            ->whereNotExists(function ($q) use ($eventId) {
                                $q->select(DB::raw(1))
                                    ->from('event_registrations')
                                    ->whereColumn('event_registrations.contact_id', 'contacts.id')
                                    ->where('event_id', '!=', $eventId);
                            })
                            ->whereNotExists(function ($q) {
                                $q->select(DB::raw(1))
                                    ->from('memberships')
                                    ->whereColumn('memberships.contact_id', 'contacts.id');
                            })
                            ->whereNotExists(function ($q) {
                                $q->select(DB::raw(1))
                                    ->from('donations')
                                    ->whereColumn('donations.contact_id', 'contacts.id');
                            })
                            ->get();

                        foreach ($eligible as $contact) {
                            $contact->delete();
                            $contactCount++;
                        }
                    }

                    $registrationCount = DB::table('event_registrations')
                        ->where('event_id', $eventId)
                        ->delete();

                    $event->update(['registrants_deleted_at' => now()]);

                    $contactWord = $contactCount === 1 ? 'contact' : 'contacts';
                    $regWord     = $registrationCount === 1 ? 'record' : 'records';

                    Notification::make()
                        ->title("{$contactCount} {$contactWord} removed, {$registrationCount} registration {$regWord} deleted.")
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $event]));
                }),

            EmailPreviewWizardAction::make(
                name: 'cancelEvent',
                emailTypeName: 'Event Cancellation',
                recipientSummary: function () {
                    $event = $this->getRecord();
                    $count = $event->registrations()
                        ->where('status', 'registered')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->count();
                    return "<strong>{$count}</strong> registered attendee(s) with an email address will receive a cancellation notice for <strong>" . e($event->title) . '</strong>.';
                },
                previewHtmlResolver: fn () => $this->cancellationPreviewHtml(),
                sendCallable: function (array $data) {
                    $event = $this->getRecord();

                    $registrations = $event->registrations()
                        ->where('status', 'registered')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->get();

                    $sent = 0;

                    foreach ($registrations as $registration) {
                        Mail::to($registration->email)->send(new EventCancellation($registration));
                        $sent++;
                    }

                    $event->update(['status' => 'cancelled']);

                    Notification::make()
                        ->title('Event cancelled')
                        ->body("Sent {$sent} cancellation " . ($sent === 1 ? 'email' : 'emails') . '.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $event]));
                },
                submitLabel: 'Cancel Event',
            )
                ->label('Cancel Event')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->getRecord()->status !== 'cancelled'),

            Actions\DeleteAction::make(),
        ];
    }

    private function cancellationPreviewHtml(): string
    {
        $event        = $this->getRecord();
        $registration = $event->registrations()
            ->where('status', 'registered')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with('contact')
            ->first();

        if (! $registration) {
            return '<p style="font-family:sans-serif;padding:1em;">No registered attendees with email addresses found.</p>';
        }

        $template = EmailTemplate::forHandle('event_cancellation');
        $tokens   = [
            'first_name'  => $registration->contact?->first_name ?? $registration->name ?? '',
            'last_name'   => $registration->contact?->last_name ?? '',
            'event_title' => $event->title ?? '',
            'site_name'   => SiteSetting::get('site_name', ''),
        ];

        return $template->resolveWrapper($template->render($tokens));
    }
}
