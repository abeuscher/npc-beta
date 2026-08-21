<?php

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Session 380 backfill of the housekeeping [test-integrity] [0.363.03] gap:
 * ProductPriceObserver's archive-and-recreate flow had no real coverage (the
 * pre-364 tests passed with the observer unregistered). The Stripe boundary
 * is faked at the SDK's own HTTP client, so the REAL observer and REAL
 * StripeClient run end to end with no network — a recorded request list is
 * the proof the observer actually fired.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, params: array}> */
    public array $requests = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $body = json_encode(['id' => 'price_new_test123', 'object' => 'price', 'active' => true]);

        return [$body, 200, []];
    }
}

function fakeStripeHttp(): FakeStripeHttpClient
{
    $fake = new FakeStripeHttpClient();
    ApiRequestor::setHttpClient($fake);

    return $fake;
}

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('creates a Stripe price on save when the amount is set and none is recorded', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = fakeStripeHttp();

    $product = Product::factory()->create(['name' => 'Tote Bag']);
    $price   = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Standard',
        'amount'     => 25.00,
    ]);

    expect($fake->requests)->toHaveCount(1)
        ->and($fake->requests[0]['method'])->toBe('post')
        ->and($fake->requests[0]['url'])->toEndWith('/v1/prices')
        ->and($fake->requests[0]['params']['unit_amount'])->toBe(2500)
        ->and($fake->requests[0]['params']['currency'])->toBe('usd')
        ->and($fake->requests[0]['params']['product_data']['name'])->toBe('Tote Bag — Standard');

    expect($price->fresh()->stripe_price_id)->toBe('price_new_test123');
});

it('archives the old Stripe price and creates a replacement when the amount changes', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = fakeStripeHttp();

    $product = Product::factory()->create(['name' => 'Tote Bag']);
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'label'           => 'Standard',
        'amount'          => 25.00,
        'stripe_price_id' => 'price_old_abc',
    ]);

    // Creation with a recorded Stripe id makes no calls (nothing changed).
    expect($fake->requests)->toHaveCount(0);

    $price->update(['amount' => 30.00]);

    expect($fake->requests)->toHaveCount(2)
        ->and($fake->requests[0]['url'])->toEndWith('/v1/prices/price_old_abc')
        ->and($fake->requests[0]['params'])->toBe(['active' => 'false']) // the SDK stringifies booleans at the HTTP layer
        ->and($fake->requests[1]['url'])->toEndWith('/v1/prices')
        ->and($fake->requests[1]['params']['unit_amount'])->toBe(3000);

    expect($price->fresh()->stripe_price_id)->toBe('price_new_test123');
});

it('stays inert when payments is not configured', function () {
    $fake = fakeStripeHttp();

    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'amount'     => 25.00,
    ]);
    $price->update(['amount' => 30.00]);

    expect($fake->requests)->toHaveCount(0)
        ->and($price->fresh()->stripe_price_id)->toBeNull();
});
