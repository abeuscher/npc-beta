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
            'events'    => Event::factory()->count($count)->create(),
            'donations' => Donation::factory()->count($count)->create(),
        };

        $this->feedback = "Created {$count} {$this->type}.";
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
