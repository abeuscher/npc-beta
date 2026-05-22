<?php

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductPrice;
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

it('duplicate produces a draft, un-archived copy with -copy slug and price tiers cloned', function () {
    $product = Product::factory()->create([
        'name'        => 'Garden Plot',
        'slug'        => 'garden-plot',
        'status'      => 'published',
        'is_archived' => true,
        'source'      => Source::IMPORT,
    ]);
    ProductPrice::factory()->for($product)->create(['label' => 'Standard', 'amount' => 50, 'sort_order' => 0, 'stripe_price_id' => 'price_abc']);
    ProductPrice::factory()->for($product)->create(['label' => 'Member', 'amount' => 30, 'sort_order' => 1]);

    $copy = $product->duplicate();

    expect($copy->id)->not->toBe($product->id);
    expect($copy->name)->toBe('Copy of Garden Plot');
    expect($copy->slug)->toBe('garden-plot-copy');
    expect($copy->status)->toBe('draft');
    expect($copy->published_at)->toBeNull();
    expect($copy->is_archived)->toBeFalse();
    expect($copy->source)->toBe(Source::HUMAN);

    expect($copy->prices()->count())->toBe(2);
    $standard = $copy->prices()->where('label', 'Standard')->first();
    expect((float) $standard->amount)->toBe(50.0);
    expect($standard->stripe_price_id)->toBeNull();
});

it('list-level duplicate action creates a copy and redirects to its editor', function () {
    $product = Product::factory()->create(['slug' => 'tote-bag', 'status' => 'published']);

    Livewire::actingAs($this->user)
        ->test(ProductResource\Pages\ListProducts::class)
        ->callTableAction('duplicate', $product)
        ->assertRedirect(ProductResource::getUrl('edit', [
            'record' => Product::where('slug', 'tote-bag-copy')->firstOrFail(),
        ]));

    expect(Product::where('slug', 'tote-bag-copy')->where('status', 'draft')->exists())->toBeTrue();
});
