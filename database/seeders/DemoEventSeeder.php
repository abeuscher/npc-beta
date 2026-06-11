<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\SampleImage;
use App\Models\User;
use App\Services\SampleImageLibrary;
use Illuminate\Database\Seeder;

/**
 * Shared demo data for the event widgets (EventDescription, EventImage,
 * EventRegistration). The dev thumbnail render seeds this so each event widget
 * has a real event (with a header image) to resolve by slug, instead of
 * rendering blank against an empty events table. Idempotent.
 */
class DemoEventSeeder extends Seeder
{
    public const EVENT_SLUG = 'demo-community-gala';

    public function run(): void
    {
        $authorId = User::query()->value('id') ?? User::factory()->create()->id;

        $event = Event::updateOrCreate(
            ['slug' => self::EVENT_SLUG],
            [
                'title'             => 'Annual Community Gala',
                'description'       => 'An evening celebrating our volunteers, partners, and the people we serve. Join us for dinner, music, and a look ahead at the year to come.',
                'status'            => 'published',
                'registration_mode' => 'open',
                'address_line_1'    => '500 Harbor View Drive',
                'city'              => 'Springfield',
                'state'             => 'IL',
                'starts_at'         => now()->addDays(21)->setTime(18, 0),
                'ends_at'           => now()->addDays(21)->setTime(22, 0),
                'author_id'         => $authorId,
            ]
        );

        if ($event->getFirstMedia('event_header') === null) {
            $this->call(SampleImageLibrarySeeder::class);
            $photo = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_STILL_PHOTOS, 1)->first();

            if ($photo) {
                try {
                    $event->addMedia($photo->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection('event_header');
                } catch (\Throwable $e) {
                    $this->command?->warn("Could not attach demo event header: {$e->getMessage()}");
                }
            }
        }
    }
}
