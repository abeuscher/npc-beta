<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\SoftDeletes;

class DashboardDebugGeneratorWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-debug-generator-widget';

    protected static ?int $sort = 99;

    protected int | string | array $columnSpan = 'full';

    public string $type = 'contacts';

    public int $quantity = 10;

    public string $feedback = '';

    public static function canView(): bool
    {
        return filter_var(env('APP_DEBUG_TOOLS', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function generate(): void
    {
        $count = max(1, min(200, $this->quantity));

        match ($this->type) {
            'contacts'  => Contact::factory()->count($count)->create(),
            'events'    => $this->generateEvents($count),
            'donations' => Donation::factory()->count($count)->create(),
        };

        $this->feedback = "Created {$count} {$this->type}.";
    }

    protected function generateEvents(int $count): void
    {
        // Spread events forward from 2 days out, each 3–7 days apart.
        // Start times land on the quarter-hour between 9am and 8pm.
        // Duration is 30 min – 3 hrs in 15-min steps.
        $cursor = now()->addDays(2)->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            $cursor->addDays(rand(3, 7));

            $hourOffset    = rand(9 * 4, 20 * 4);   // quarters from midnight: 9am–8pm
            $startsAt      = $cursor->copy()->addMinutes($hourOffset * 15);
            $durationSteps = rand(2, 12);             // 2–12 × 15 min = 30 min – 3 hrs
            $endsAt        = $startsAt->copy()->addMinutes($durationSteps * 15);

            Event::factory()->create([
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
            ]);
        }
    }

    public function wipe(): void
    {
        $model = match ($this->type) {
            'contacts'  => Contact::class,
            'events'    => Event::class,
            'donations' => Donation::class,
        };

        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            $model::withTrashed()->forceDelete();
        } else {
            $model::query()->delete();
        }

        $this->feedback = "All {$this->type} deleted.";
    }
}
