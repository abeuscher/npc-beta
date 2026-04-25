<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_widgets')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $decoded = is_string($row->query_config) ? json_decode($row->query_config, true) : $row->query_config;
                if (! is_array($decoded) || $decoded === []) {
                    continue;
                }

                $hasArray = false;
                $hasScalar = false;
                foreach ($decoded as $value) {
                    if (is_array($value)) {
                        $hasArray = true;
                    } else {
                        $hasScalar = true;
                    }
                }

                if (! $hasArray) {
                    continue;
                }

                $flat = [];
                foreach ($decoded as $slotKey => $slotValue) {
                    if (! is_array($slotValue)) {
                        $flat[$slotKey] = $slotValue;
                        continue;
                    }
                    foreach ($slotValue as $k => $v) {
                        if (array_key_exists($k, $flat) && $flat[$k] !== $v) {
                            Log::info('flatten_page_widgets_query_config: key conflict on row', [
                                'page_widget_id' => $row->id,
                                'key'            => $k,
                                'previous'       => $flat[$k],
                                'overwrite_with' => $v,
                            ]);
                        }
                        $flat[$k] = $v;
                    }
                }

                DB::table('page_widgets')
                    ->where('id', $row->id)
                    ->update(['query_config' => json_encode($flat)]);
            }
        });
    }

    public function down(): void
    {
        Log::warning('flatten_page_widgets_query_config: down() is irreversible without a snapshot — slot keys are not recoverable from flat data.');
    }
};
