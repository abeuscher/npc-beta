<?php

namespace App\Widgets\EventCalendar;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->orderBy('id')->first()
            ?? User::factory()->create();

        $occurrences = [
            ['days' => 4,  'slug' => 'demo-event-1', 'title' => 'Community Volunteer Day'],
            ['days' => 9,  'slug' => 'demo-event-2', 'title' => 'Quarterly Board Meeting'],
            ['days' => 16, 'slug' => 'demo-event-3', 'title' => 'Spring Fundraising Gala'],
        ];

        foreach ($occurrences as $occurrence) {
            $startsAt = now()->addDays($occurrence['days'])->setTime(18, 0);

            Event::updateOrCreate(
                ['slug' => $occurrence['slug']],
                [
                    'title'             => $occurrence['title'],
                    'author_id'         => $author->id,
                    'description'       => 'Sample event for widget demos.',
                    'status'            => 'published',
                    'starts_at'         => $startsAt,
                    'ends_at'           => $startsAt->copy()->addHours(2),
                    'price'             => 0,
                    'registration_mode' => 'open',
                ]
            );
        }
    }
}
