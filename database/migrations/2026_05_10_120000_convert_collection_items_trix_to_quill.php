<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Support\HtmlSanitizer;
use App\Support\TrixToQuillConverter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Collection::all()->each(function (Collection $collection) {
            $richTextKeys = collect($collection->fields ?? [])
                ->where('type', 'rich_text')
                ->pluck('key')
                ->filter()
                ->all();

            if ($richTextKeys === []) {
                return;
            }

            CollectionItem::query()
                ->where('collection_id', $collection->id)
                ->chunkById(200, function ($items) use ($richTextKeys) {
                    foreach ($items as $item) {
                        $data    = $item->data ?? [];
                        $changed = false;

                        foreach ($richTextKeys as $key) {
                            if (! isset($data[$key]) || ! is_string($data[$key])) {
                                continue;
                            }

                            $next = HtmlSanitizer::sanitize(TrixToQuillConverter::convert($data[$key]));

                            if ($next !== $data[$key]) {
                                $data[$key] = $next;
                                $changed    = true;
                            }
                        }

                        if ($changed) {
                            $item->data = $data;
                            $item->saveQuietly();
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        // No reverse path: pre-beta license; old Trix shape disappears from history.
    }
};
