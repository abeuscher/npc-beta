<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\EventDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders
        {--days=1 : Days before the event date to send}';

    protected $description = 'Send reminder emails to registrants whose event dates fall within the given number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $dates = EventDate::with('event.registrations')
            ->published()
            ->whereBetween('starts_at', [now(), now()->addDays($days)->endOfDay()])
            ->get();

        $sent = 0;

        foreach ($dates as $eventDate) {
            $event = $eventDate->event;

            if (! $event) {
                continue;
            }

            $registrations = $event->registrations
                ->where('status', 'registered')
                ->filter(fn ($reg) => ! empty($reg->email));

            foreach ($registrations as $registration) {
                Mail::to($registration->email)->send(new EventReminder($registration, $eventDate));
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminders for {$dates->count()} event dates.");

        return self::SUCCESS;
    }
}
