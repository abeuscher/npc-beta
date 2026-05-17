<?php

use App\Services\ThemeColorRelocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'primary_color',
        'header_bg_color',
        'footer_bg_color',
        'nav_link_color',
        'nav_hover_color',
        'nav_active_color',
    ];

    public function up(): void
    {
        // Columns still physically present here — copy before the drop.
        $default = DB::table('templates')
            ->where('type', 'page')
            ->where('is_default', true)
            ->first();

        if ($default !== null) {
            $columns = [];
            foreach (self::COLUMNS as $c) {
                $columns[$c] = $default->{$c} ?? null;
            }
            $map = ThemeColorRelocation::mapTemplateColors($columns);

            DB::table('site_settings')->updateOrInsert(
                ['key' => 'theme_colors'],
                [
                    'value'      => json_encode($map),
                    'type'       => 'json',
                    'group'      => 'design',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        // Surface (do not silently discard) non-default page templates whose
        // colours diverge — they have no Theme home until the 299 schemes
        // land. Recorded decision: interim per-template-colour loss accepted
        // (session 297 Open Question 1).
        $divergent = DB::table('templates')
            ->where('type', 'page')
            ->where('is_default', false)
            ->where(function ($q) {
                foreach (self::COLUMNS as $c) {
                    $q->orWhereNotNull($c);
                }
            })
            ->get(['id', 'name', ...self::COLUMNS]);

        if ($divergent->isNotEmpty()) {
            Log::warning('[s297 relocation] Non-default page templates carried divergent colours with no Theme home until 299 schemes; values logged, columns dropped per accepted Open Question 1.', [
                'count'     => $divergent->count(),
                'templates' => $divergent->map(fn ($t) => (array) $t)->all(),
            ]);
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }

    public function down(): void
    {
        // Schema-shape restore only — a one-shot data migration is not
        // reversed (the values now live in the theme_colors SiteSetting).
        Schema::table('templates', function (Blueprint $table) {
            foreach (self::COLUMNS as $c) {
                $table->string($c)->nullable();
            }
        });
    }
};
