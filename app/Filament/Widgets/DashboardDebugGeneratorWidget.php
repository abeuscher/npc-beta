<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Fund;
use App\Models\Membership;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class DashboardDebugGeneratorWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-debug-generator-widget';

    protected static ?int $sort = 9;

    protected int | string | array $columnSpan = 'full';

    public string $type = 'contacts';

    public int $quantity = 10;

    public string $gmailBase = '';

    public int $taxYear;

    public string $feedback = '';

    public function mount(): void
    {
        $this->taxYear = (int) now()->year;
    }

    public static function canView(): bool
    {
        return filter_var(env('APP_DEBUG_TOOLS', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function generate(): void
    {
        $count = max(1, min(200, $this->quantity));

        match ($this->type) {
            'contacts'   => $this->generateContacts($count),
            'events'     => $this->generateEvents($count),
            'donations'  => $this->generateDonations($count),
            'purchases'  => $this->generatePurchases($count),
            'products'   => $this->generateProducts($count),
            'members'    => $this->generateMembers($count),
            'blog_posts' => $this->generateBlogPosts($count),
        };

        if (! $this->feedback) {
            $this->feedback = "Created {$count} {$this->type}.";
        }
    }

    protected function generateContacts(int $count): void
    {
        $factory = Contact::factory()->count($count);

        if (trim($this->gmailBase) !== '') {
            $factory = $factory->withGmailBase(trim($this->gmailBase));
        }

        $factory->create();
    }

    protected function generateEvents(int $count): void
    {
        // Spread events forward from 2 days out, each 3–7 days apart.
        // Start times land on the quarter-hour between 9am and 8pm.
        // Duration is 30 min – 3 hrs in 15-min steps.
        $cursor = now()->addDays(2)->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            $cursor->addDays(rand(3, 7));

            $hourOffset    = rand(9 * 4, 20 * 4);   // quarters from midnight: 9am–8pm
            $startsAt      = $cursor->copy()->addMinutes($hourOffset * 15);
            $durationSteps = rand(2, 12);             // 2–12 × 15 min = 30 min – 3 hrs
            $endsAt        = $startsAt->copy()->addMinutes($durationSteps * 15);

            Event::factory()->create([
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
            ]);
        }
    }

    protected function generateDonations(int $count): void
    {
        $contactIds = Contact::pluck('id')->all();

        if (empty($contactIds)) {
            $this->feedback = 'No contacts found — generate contacts first.';
            return;
        }

        $fundIds = Fund::where('is_active', true)->pluck('id')->all();
        $year    = (int) $this->taxYear;

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear   = Carbon::create($year, 12, 31)->endOfDay();

        for ($i = 0; $i < $count; $i++) {
            $startedAt = fake()->dateTimeBetween($startOfYear, $endOfYear);

            $donation = Donation::factory()->create([
                'contact_id' => fake()->randomElement($contactIds),
                'fund_id'    => $fundIds ? fake()->randomElement($fundIds) : null,
                'started_at' => $startedAt,
                'status'     => 'active',
            ]);

            Transaction::create([
                'subject_type' => Donation::class,
                'subject_id'   => $donation->id,
                'contact_id'   => $donation->contact_id,
                'type'         => 'payment',
                'amount'       => $donation->amount,
                'direction'    => 'in',
                'status'       => 'completed',
                'stripe_id'    => null,
                'occurred_at'  => $donation->started_at,
            ]);
        }
    }

    protected function generatePurchases(int $count): void
    {
        $contactIds = Contact::pluck('id')->all();

        if (empty($contactIds)) {
            $this->feedback = 'No contacts found — generate contacts first.';
            return;
        }

        $products = Product::where('status', 'active')->has('prices')->with('prices')->get();

        if ($products->isEmpty()) {
            $this->feedback = 'No active products with prices found — generate products first.';
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $product   = $products->random();
            $price     = $product->prices->random();
            $contactId = fake()->randomElement($contactIds);

            $purchase = Purchase::factory()->create([
                'product_id'       => $product->id,
                'product_price_id' => $price->id,
                'contact_id'       => $contactId,
                'amount_paid'      => $price->amount,
            ]);

            Transaction::create([
                'subject_type' => Purchase::class,
                'subject_id'   => $purchase->id,
                'contact_id'   => $purchase->contact_id,
                'type'         => 'payment',
                'amount'       => $purchase->amount_paid,
                'direction'    => 'in',
                'status'       => 'completed',
                'stripe_id'    => null,
                'occurred_at'  => $purchase->occurred_at,
            ]);
        }
    }

    protected function generateProducts(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $product    = Product::factory()->create(['sort_order' => $i + 1]);
            $priceCount = rand(1, 3);

            for ($j = 0; $j < $priceCount; $j++) {
                ProductPrice::factory()->create([
                    'product_id' => $product->id,
                    'sort_order' => $j + 1,
                ]);
            }
        }
    }

    protected function generateMembers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $contact = Contact::factory()->create([
                'email'               => fake()->unique()->safeEmail(),
                'address_line_1'      => fake()->streetAddress(),
                'address_line_2'      => fake()->optional(0.2)->secondaryAddress(),
                'city'                => fake()->city(),
                'state'               => fake()->stateAbbr(),
                'postal_code'         => fake()->postcode(),
                'date_of_birth'       => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                'mailing_list_opt_in' => true,
            ]);

            Membership::factory()->create(['contact_id' => $contact->id]);

            PortalAccount::create([
                'contact_id'        => $contact->id,
                'email'             => $contact->email,
                'password'          => Hash::make('thisisthepassword'),
                'email_verified_at' => now(),
                'is_active'         => true,
            ]);
        }
    }

    protected function generateBlogPosts(int $count): void
    {
        $blogPrefix = config('site.blog_prefix', 'news');
        $userIds    = \App\Models\User::pluck('id')->toArray();

        for ($i = 0; $i < $count; $i++) {
            $title       = fake()->sentence(rand(4, 8));
            $baseSlug    = \Illuminate\Support\Str::slug($title) . '-' . fake()->unique()->randomNumber(4);
            $publishedAt = fake()->dateTimeBetween('-2 years', 'now');

            Page::create([
                'author_id'    => fake()->randomElement($userIds),
                'title'        => $title,
                'slug'         => $blogPrefix . '/' . $baseSlug,
                'type'         => 'post',
                'status'       => 'published',
                'published_at' => $publishedAt,
            ]);
        }
    }

    public function wipe(): void
    {
        match ($this->type) {
            'donations'  => $this->wipeDonations(),
            'purchases'  => $this->wipePurchases(),
            'products'   => $this->wipeProducts(),
            'blog_posts' => $this->wipeBlogPosts(),
            default      => $this->wipeGeneric(),
        };

        $this->feedback = "All {$this->type} deleted.";
    }

    private function wipeDonations(): void
    {
        Transaction::where('subject_type', Donation::class)->delete();
        Donation::query()->delete();
    }

    private function wipePurchases(): void
    {
        Transaction::where('subject_type', Purchase::class)->delete();
        Purchase::query()->delete();
    }

    private function wipeProducts(): void
    {
        ProductPrice::query()->delete();
        Product::query()->delete();
    }

    private function wipeBlogPosts(): void
    {
        Page::withTrashed()->where('type', 'post')->forceDelete();
    }

    private function wipeGeneric(): void
    {
        $model = match ($this->type) {
            'contacts' => Contact::class,
            'events'   => Event::class,
            'members'  => Membership::class,
        };

        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            $model::withTrashed()->forceDelete();
        } else {
            $model::query()->delete();
        }
    }
}
