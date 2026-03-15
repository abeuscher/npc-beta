<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Mail\EventReminder;
use App\Models\EventDate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
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
                    $event = $this->getRecord();
                    $hasUpcoming = $event->eventDates()->where('starts_at', '>=', now())->exists();
                    $hasEmails   = $event->registrations()->where('status', 'registered')->whereNotNull('email')->where('email', '!=', '')->exists();
                    return $hasUpcoming && $hasEmails;
                })
                ->action(function () {
                    $event = $this->getRecord();

                    $upcomingDates = $event->eventDates()
                        ->published()
                        ->where('starts_at', '>=', now())
                        ->orderBy('starts_at')
                        ->get();

                    $registrations = $event->registrations()
                        ->where('status', 'registered')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->get();

                    $sent = 0;

                    foreach ($upcomingDates as $eventDate) {
                        foreach ($registrations as $registration) {
                            Mail::to($registration->email)->send(new EventReminder($registration, $eventDate));
                            $sent++;
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Reminders sent')
                        ->body("Sent {$sent} reminder " . ($sent === 1 ? 'email' : 'emails') . '.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
