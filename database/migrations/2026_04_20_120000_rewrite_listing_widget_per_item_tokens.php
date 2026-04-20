<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Per-widget token rewrite spec.
     *
     * For each listing widget, the config field holding a user-editable
     * per-item template string, and the key list whose bare `{{key}}` form
     * is rewritten to namespaced `{{item.key}}`.
     *
     * Carousel uses the narrow set (the six PageContextTokens::TOKENS keys)
     * because the collection-item shape is data-driven — rewriting every
     * `{{key}}` would risk clobbering intentional page-context references.
     */
    private const WIDGET_SPECS = [
        'events_listing' => [
            'field' => 'content_template',
            'keys'  => ['title', 'slug', 'url', 'date', 'date_iso', 'ends_at', 'location', 'is_free', 'price_badge', 'image'],
        ],
        'blog_listing' => [
            'field' => 'content_template',
            'keys'  => ['title', 'slug', 'url', 'date', 'date_iso', 'excerpt', 'image'],
        ],
        'carousel' => [
            'field' => 'caption_template',
            'keys'  => ['title', 'date', 'excerpt', 'author', 'starts_at', 'location'],
        ],
    ];

    public function up(): void
    {
        $this->rewrite(direction: 'up');
    }

    public function down(): void
    {
        $this->rewrite(direction: 'down');
    }

    private function rewrite(string $direction): void
    {
        foreach (self::WIDGET_SPECS as $handle => $spec) {
            $typeId = DB::table('widget_types')->where('handle', $handle)->value('id');
            if (! $typeId) {
                Log::info("[widget-token-migration] handle={$handle} direction={$direction} skipped (widget_type row missing)");
                continue;
            }

            $scanned   = 0;
            $rewritten = 0;
            $sample    = null;

            DB::table('page_widgets')
                ->where('widget_type_id', $typeId)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($spec, $direction, &$scanned, &$rewritten, &$sample) {
                    foreach ($rows as $row) {
                        $scanned++;

                        $config = json_decode($row->config ?? 'null', true);
                        if (! is_array($config)) {
                            continue;
                        }

                        $field = $spec['field'];
                        $original = $config[$field] ?? null;
                        if (! is_string($original) || $original === '') {
                            continue;
                        }

                        $updated = $this->transform($original, $spec['keys'], $direction);

                        if ($updated === $original) {
                            continue;
                        }

                        $config[$field] = $updated;
                        DB::table('page_widgets')
                            ->where('id', $row->id)
                            ->update(['config' => json_encode($config)]);

                        $rewritten++;
                        if ($sample === null) {
                            $sample = ['id' => $row->id, 'before' => $original, 'after' => $updated];
                        }
                    }
                });

            $summary = "[widget-token-migration] handle={$handle} direction={$direction} scanned={$scanned} rewritten={$rewritten}";
            if ($sample !== null) {
                $summary .= ' sample_id=' . $sample['id']
                    . ' before=' . json_encode($sample['before'])
                    . ' after=' . json_encode($sample['after']);
            }
            Log::info($summary);
        }
    }

    private function transform(string $text, array $keys, string $direction): string
    {
        foreach ($keys as $key) {
            $bare       = '{{' . $key . '}}';
            $namespaced = '{{item.' . $key . '}}';

            if ($direction === 'up') {
                $text = str_replace($bare, $namespaced, $text);
            } else {
                $text = str_replace($namespaced, $bare, $text);
            }
        }

        return $text;
    }
};
