<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\WidgetType;

class DemoDataService
{
    /**
     * Generate plausible sample config data for a widget type.
     *
     * Returns an array in the same shape the widget template expects
     * from $config — keyed by the config_schema field keys.
     */
    public function generateForWidget(WidgetType $widgetType): array
    {
        $config = [];

        foreach ($widgetType->config_schema ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            $type    = $field['type'] ?? 'text';
            $subtype = $field['subtype'] ?? null;

            $config[$key] = $this->generateFieldValue($type, $subtype, $field);
        }

        return $config;
    }

    /**
     * Generate sample collection data matching the shape WidgetDataResolver produces.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateCollectionData(string $sourceType, int $count = 3, ?Collection $collection = null): array
    {
        return match ($sourceType) {
            'events'     => $this->generateEventItems($count),
            'blog_posts' => $this->generateBlogPostItems($count),
            'products'   => $this->generateProductItems($count),
            'custom'     => $this->generateCustomItems($count, $collection),
            default      => [],
        };
    }

    /**
     * Generate a single field value based on type + optional subtype.
     */
    public function generateFieldValue(string $type, ?string $subtype = null, array $fieldDef = []): mixed
    {
        return match ($type) {
            'richtext'   => $this->generateRichtext($subtype),
            'text'       => $this->generateText($subtype),
            'textarea'   => $this->generateTextarea(),
            'number'     => $this->generateNumber($subtype, $fieldDef),
            'color'      => $this->generateColor(),
            'select'     => $this->generateSelect($fieldDef),
            'toggle'     => true,
            'image'      => null,
            'video'      => null,
            'url'        => 'https://example.com',
            'buttons'    => $this->generateButtons(),
            'checkboxes' => $this->generateCheckboxes($fieldDef),
            default      => '',
        };
    }

    // ── Type generators ─────────────────────────────────────────────────

    private function generateRichtext(?string $subtype): string
    {
        return match ($subtype) {
            'article' => $this->generateArticleRichtext(),
            default   => $this->generateDefaultRichtext(),
        };
    }

    private function generateDefaultRichtext(): string
    {
        $p1 = fake()->paragraph(3);
        $p2 = fake()->paragraph(2);

        return "<p>{$p1}</p>\n<p>{$p2}</p>";
    }

    private function generateArticleRichtext(): string
    {
        $heading = fake()->sentence(4);
        $p1      = fake()->paragraph(4);
        $p2      = fake()->paragraph(3);
        $items   = collect(range(1, 3))->map(fn () => '<li>' . fake()->sentence() . '</li>')->implode("\n");
        $p3      = fake()->paragraph(3);
        $bold    = fake()->words(3, true);
        $p4      = fake()->paragraph(2);

        return "<h2>{$heading}</h2>\n<p>{$p1}</p>\n<p>{$p2}</p>\n<ul>\n{$items}\n</ul>\n<p>As <strong>{$bold}</strong> demonstrates, {$p3}</p>\n<p>{$p4}</p>";
    }

    private function generateText(?string $subtype): string
    {
        return match ($subtype) {
            'title'       => ucfirst(fake()->words(rand(4, 8), true)),
            'email'       => 'jane.doe@example.org',
            'url'         => 'https://example.com/page',
            'phone'       => '(555) 867-5309',
            'state'       => fake()->stateAbbr(),
            'zip'         => fake()->postcode(),
            'city'        => fake()->city(),
            'address'     => fake()->streetAddress(),
            'person_name' => fake()->name(),
            default       => ucfirst(fake()->words(rand(2, 5), true)),
        };
    }

    private function generateTextarea(): string
    {
        return fake()->paragraph(2);
    }

    private function generateNumber(?string $subtype, array $fieldDef): int|float
    {
        if ($subtype === 'currency') {
            return round(rand(500, 50000) / 100, 2);
        }

        $default = $fieldDef['default'] ?? null;
        if ($default !== null) {
            return $default;
        }

        return rand(1, 100);
    }

    private function generateColor(): string
    {
        return sprintf('#%06x', rand(0, 0xFFFFFF));
    }

    private function generateSelect(array $fieldDef): string
    {
        if (! empty($fieldDef['default'])) {
            return $fieldDef['default'];
        }

        $options = $fieldDef['options'] ?? [];
        if (! empty($options)) {
            $keys = array_keys($options);

            return (string) $keys[0];
        }

        return '';
    }

    private function generateButtons(): array
    {
        return [
            ['text' => 'Learn More', 'url' => '#', 'style' => 'primary'],
            ['text' => 'Contact Us', 'url' => '#', 'style' => 'secondary'],
        ];
    }

    private function generateCheckboxes(array $fieldDef): array
    {
        if (! empty($fieldDef['default'])) {
            return $fieldDef['default'];
        }

        $options = $fieldDef['options'] ?? [];
        if (! empty($options)) {
            return array_slice(array_keys($options), 0, min(3, count($options)));
        }

        return [];
    }

    // ── Collection data generators ──────────────────────────────────────

    private function generateEventItems(int $count): array
    {
        $items  = [];
        $cursor = now()->addDays(3);

        for ($i = 0; $i < $count; $i++) {
            $cursor->addDays(rand(2, 7));
            $startsAt = $cursor->copy()->setHour(rand(9, 18));
            $endsAt   = $startsAt->copy()->addHours(rand(1, 4));
            $isFree   = $i % 2 === 0;

            $items[] = [
                'id'            => fake()->uuid(),
                'title'         => fake()->sentence(rand(3, 6)),
                'slug'          => fake()->slug(3),
                'starts_at'     => $startsAt->toIso8601String(),
                'ends_at'       => $endsAt->toIso8601String(),
                'is_virtual'    => $i % 3 === 0,
                'is_free'       => $isFree,
                'url'           => 'https://example.com/events',
                'thumbnail_url' => null,
            ];
        }

        return $items;
    }

    private function generateBlogPostItems(int $count): array
    {
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'id'            => fake()->uuid(),
                'title'         => fake()->sentence(rand(4, 8)),
                'slug'          => fake()->slug(3),
                'published_at'  => now()->subDays(rand(1, 365))->toIso8601String(),
                'thumbnail_url' => null,
            ];
        }

        return $items;
    }

    private function generateProductItems(int $count): array
    {
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $capacity  = rand(10, 200);
            $available = rand(0, $capacity);

            $items[] = [
                'id'          => fake()->uuid(),
                'name'        => ucfirst(fake()->words(rand(2, 4), true)),
                'slug'        => fake()->slug(2),
                'description' => fake()->sentence(),
                'capacity'    => $capacity,
                'available'   => $available,
                'image_url'   => null,
                'prices'      => [
                    [
                        'id'              => fake()->uuid(),
                        'label'           => 'Standard',
                        'amount'          => rand(10, 200) * 100,
                        'stripe_price_id' => null,
                    ],
                ],
            ];
        }

        return $items;
    }

    private function generateCustomItems(int $count, ?Collection $collection): array
    {
        if (! $collection) {
            return [];
        }

        $fields = $collection->fields ?? [];
        $items  = [];

        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($fields as $field) {
                $key  = $field['key'] ?? null;
                $type = $field['type'] ?? 'text';
                if (! $key) {
                    continue;
                }

                $row[$key] = $this->generateFieldValue($type, $field['subtype'] ?? null, $field);
            }
            $items[] = $row;
        }

        return $items;
    }
}
