<?php

namespace App\Observers;

use App\Models\CustomFieldDef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomFieldDefObserver
{
    public function saved(CustomFieldDef $field): void
    {
        if ($field->model_type !== 'contact') {
            return;
        }

        $handle    = $field->handle;
        $indexName = "idx_contacts_cf_{$handle}";

        if ($field->is_filterable) {
            try {
                DB::statement(
                    "CREATE INDEX IF NOT EXISTS {$indexName} ON contacts ((custom_fields->>'$handle'))"
                );
            } catch (\Throwable $e) {
                Log::error("Failed to create expression index {$indexName}: " . $e->getMessage());
            }
        } else {
            // Only drop if is_filterable was previously true
            if ($field->wasChanged('is_filterable')) {
                try {
                    DB::statement("DROP INDEX IF EXISTS {$indexName}");
                } catch (\Throwable $e) {
                    Log::error("Failed to drop expression index {$indexName}: " . $e->getMessage());
                }
            }
        }
    }
}
