<?php

namespace App\Widgets\EventCalendar;

use App\Models\Event;
use App\Models\SampleImage;
use App\Models\User;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->orderBy('id')->first()
            ?? User::factory()->create();

        $occurrences = [
            [
                'days'           => 4,
                'slug'           => 'demo-event-1',
                'title'          => 'Bedrock Community Dig',
                'description'    => 'A hands-on afternoon clearing brush along the quarry trail. Bring gloves.',
                'address_line_1' => '14 Granite Row',
                'city'           => 'Bedrock',
                'state'          => 'CA',
            ],
            [
                'days'           => 9,
                'slug'           => 'demo-event-2',
                'title'          => 'Moose Lodge Annual Meeting',
                'description'    => 'Members convene to vote on the slate and review the treasurer\'s report.',
                'address_line_1' => '221 Frostbite Lane',
                'city'           => 'Frostbite Falls',
                'state'          => 'MN',
            ],
            [
                'days'           => 16,
                'slug'           => 'demo-event-3',
                'title'          => 'Annual Anvil Drop Gala',
                'description'    => 'Black-tie fundraiser under the big top. Silent auction, dinner, live music.',
                'address_line_1' => '9 Tunnel Road',
                'city'           => 'Toontown',
                'state'          => 'CA',
            ],
        ];

        $this->call(SampleImageLibrarySeeder::class);
        $pool = app(SampleImageLibrary::class);

        foreach ($occurrences as $occurrence) {
            $startsAt = now()->addDays($occurrence['days'])->setTime(18, 0);

            $event = Event::updateOrCreate(
                ['slug' => $occurrence['slug']],
                [
                    'title'             => $occurrence['title'],
                    'author_id'         => $author->id,
                    'description'       => $occurrence['description'],
                    'status'            => 'published',
                    'address_line_1'    => $occurrence['address_line_1'],
                    'city'              => $occurrence['city'],
                    'state'             => $occurrence['state'],
                    'starts_at'         => $startsAt,
                    'ends_at'           => $startsAt->copy()->addHours(2),
                    'price'             => 0,
                    'registration_mode' => 'open',
                ]
            );

            foreach (['event_thumbnail', 'event_header'] as $collection) {
                if ($event->getMedia($collection)->isNotEmpty()) {
                    continue;
                }
                $source = $pool->random(SampleImage::CATEGORY_STILL_PHOTOS, 1)->first();
                if ($source === null) {
                    continue;
                }
                try {
                    $event->addMedia($source->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection($collection);
                } catch (\Throwable $e) {
                    // Silent: image attachment is a demo nicety.
                }
            }
        }
    }
}
