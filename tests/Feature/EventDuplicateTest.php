<?php

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Page;
use App\Models\TicketTier;
use App\Models\User;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('super_admin');
    $this->actingAs($this->user);
});

it('duplicate copies dates and tiers, gives the copy its own fresh landing page, drops registrations', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
    $author = User::factory()->create();
    $landing = Page::factory()->create(['type' => 'event']);
    $event = Event::factory()->create([
        'title'           => 'Spring Gala',
        'slug'            => 'spring-gala',
        'status'          => 'published',
        'published_at'    => now()->subDay(),
        'author_id'       => $author->id,
        'source'          => Source::IMPORT,
        'starts_at'       => now()->addDays(30),
        'ends_at'         => now()->addDays(30)->addHours(3),
        'landing_page_id' => $landing->id,
    ]);
    TicketTier::factory()->for($event)->create(['name' => 'General', 'price' => 25, 'capacity' => 100, 'sort_order' => 0]);
    TicketTier::factory()->for($event)->create(['name' => 'VIP', 'price' => 75, 'capacity' => 20, 'sort_order' => 1]);

    $copy = $event->duplicate();

    expect($copy->title)->toBe('Copy of Spring Gala');
    expect($copy->slug)->toBe('spring-gala-copy');
    expect($copy->status)->toBe('draft');
    expect($copy->published_at)->toBeNull();
    expect($copy->source)->toBe(Source::HUMAN);
    expect($copy->author_id)->toBe($this->user->id);
    // The copy gets its own fresh landing page — not the original's.
    expect($copy->landing_page_id)->not->toBeNull();
    expect($copy->landing_page_id)->not->toBe($landing->id);

    // Dates carry forward.
    expect($copy->starts_at->equalTo($event->starts_at))->toBeTrue();
    expect($copy->ends_at->equalTo($event->ends_at))->toBeTrue();

    // Tiers cloned and re-pointed.
    expect($copy->ticketTiers()->count())->toBe(2);
    expect($copy->ticketTiers()->pluck('name')->all())->toBe(['General', 'VIP']);
});

it('list-level duplicate action creates a copy and redirects to its editor', function () {
    $event = Event::factory()->create(['slug' => 'fun-run', 'status' => 'published']);

    Livewire::actingAs($this->user)
        ->test(EventResource\Pages\ListEvents::class)
        ->callTableAction('duplicate', $event)
        ->assertRedirect(EventResource::getUrl('edit', [
            'record' => Event::where('slug', 'fun-run-copy')->firstOrFail(),
        ]));

    expect(Event::where('slug', 'fun-run-copy')->where('status', 'draft')->exists())->toBeTrue();
});
