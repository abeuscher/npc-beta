<?php

namespace Plugins\Events\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Plugins\Events\Mail\EventReminder;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders
        {--days=1 : Days before the event date to send}';

    protected $description = 'Send reminder emails to registrants whose event dates fall within the given number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $events = Event::with('registrations')
            ->published()
            ->whereBetween('starts_at', [now(), now()->addDays($days)->endOfDay()])
            ->get();

        $sent = 0;

        foreach ($events as $event) {
            $registrations = $event->registrations
                ->where('status', 'registered')
                ->filter(fn ($reg) => ! empty($reg->email));

            foreach ($registrations as $registration) {
                Mail::to($registration->email)->send(new EventReminder($registration, $event));
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminders for {$events->count()} events.");

        return self::SUCCESS;
    }
}
