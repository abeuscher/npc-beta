<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignUuid('ticket_tier_id')
                ->nullable()
                ->after('event_id')
                ->constrained('ticket_tiers')
                ->nullOnDelete();
        });

        $now = now();

        // Backfill: every event with a non-zero price OR a non-null capacity gets
        // one "General" tier carrying that price + capacity. Truly free-and-uncapped
        // events get no tier.
        $eventsNeedingBackfill = DB::table('events')
            ->where(function ($q) {
                $q->where('price', '>', 0)
                  ->orWhereNotNull('capacity');
            })
            ->select('id', 'price', 'capacity')
            ->get();

        foreach ($eventsNeedingBackfill as $event) {
            DB::table('ticket_tiers')->insert([
                'id'         => (string) Str::uuid(),
                'event_id'   => $event->id,
                'name'       => 'General',
                'price'      => $event->price,
                'capacity'   => $event->capacity,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Retroactive importer linkage: every event_registrations row that the
        // events importer wrote a ticket_type onto gets resolved to a tier.
        // Match by case-insensitive name; create a new tier on the event when
        // no name matches.
        $registrations = DB::table('event_registrations')
            ->whereNotNull('ticket_type')
            ->select('id', 'event_id', 'ticket_type', 'ticket_fee')
            ->get();

        foreach ($registrations as $reg) {
            $existing = DB::table('ticket_tiers')
                ->where('event_id', $reg->event_id)
                ->whereRaw('LOWER(name) = LOWER(?)', [$reg->ticket_type])
                ->select('id')
                ->first();

            if ($existing) {
                DB::table('event_registrations')
                    ->where('id', $reg->id)
                    ->update(['ticket_tier_id' => $existing->id]);
                continue;
            }

            $nextSortOrder = ((int) DB::table('ticket_tiers')
                ->where('event_id', $reg->event_id)
                ->max('sort_order')) + 1;

            $newTierId = (string) Str::uuid();
            DB::table('ticket_tiers')->insert([
                'id'         => $newTierId,
                'event_id'   => $reg->event_id,
                'name'       => $reg->ticket_type,
                'price'      => $reg->ticket_fee ?? 0,
                'capacity'   => null,
                'sort_order' => $nextSortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('event_registrations')
                ->where('id', $reg->id)
                ->update(['ticket_tier_id' => $newTierId]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['price', 'capacity']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
        });

        // Restore price/capacity from the General tier where one exists.
        $generalTiers = DB::table('ticket_tiers')
            ->where('name', 'General')
            ->where('sort_order', 0)
            ->select('event_id', 'price', 'capacity')
            ->get();

        foreach ($generalTiers as $tier) {
            DB::table('events')
                ->where('id', $tier->event_id)
                ->update([
                    'price'    => $tier->price,
                    'capacity' => $tier->capacity,
                ]);
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropForeign(['ticket_tier_id']);
            $table->dropColumn('ticket_tier_id');
        });

        Schema::dropIfExists('ticket_tiers');
    }
};
