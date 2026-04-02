<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\PortalAccount;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\WidgetType;
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

    public string $feedback = '';

    public static function canView(): bool
    {
        return filter_var(env('APP_DEBUG_TOOLS', false), FILTER_VALIDATE_BOOLEAN)
            && ! auth()->user()?->isDemo();
    }

    public function generate(): void
    {
        $count = max(1, min(200, $this->quantity));

        match ($this->type) {
            'contacts'   => $this->generateContacts($count),
            'events'     => $this->generateEvents($count),
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
        $contactIds = Contact::pluck('id')->all();
        $cursor     = now()->addDays(2)->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            $cursor->addDays(rand(3, 7));

            $hourOffset    = rand(9 * 4, 20 * 4);
            $startsAt      = $cursor->copy()->addMinutes($hourOffset * 15);
            $durationSteps = rand(2, 12);
            $endsAt        = $startsAt->copy()->addMinutes($durationSteps * 15);

            $isFree      = rand(1, 100) <= 50;
            $hasCapacity = rand(1, 100) <= 40;
            $price       = $isFree ? 0 : rand(5, 100);
            $capacity    = $hasCapacity ? rand(20, 200) : null;

            $event = Event::factory()->create([
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
                'price'     => $price,
                'capacity'  => $capacity,
            ]);

            if ($isFree) {
                $maxReg        = $capacity ? min(30, $capacity) : 30;
                $regCount      = rand(0, $maxReg);
                $usedContacts  = [];

                for ($r = 0; $r < $regCount; $r++) {
                    $contactId = null;
                    $name      = fake()->name();
                    $email     = fake()->safeEmail();

                    if (! empty($contactIds)) {
                        $available = array_diff($contactIds, $usedContacts);
                        if (! empty($available)) {
                            $contactId      = fake()->randomElement($available);
                            $usedContacts[] = $contactId;
                            $contact        = Contact::find($contactId);
                            $name           = trim($contact->first_name . ' ' . $contact->last_name);
                            if (! empty($contact->email) && filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                                $email = $contact->email;
                            }
                        }
                    }

                    EventRegistration::create([
                        'event_id'      => $event->id,
                        'contact_id'    => $contactId,
                        'name'          => $name,
                        'email'         => $email,
                        'status'        => 'registered',
                        'registered_at' => now(),
                    ]);
                }
            }
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
        $blogPrefix    = config('site.blog_prefix', 'news');
        $userIds       = \App\Models\User::pluck('id')->toArray();
        $textBlockType = WidgetType::where('handle', 'text_block')->first();
        $blogPagerType = WidgetType::where('handle', 'blog_pager')->first();

        for ($i = 0; $i < $count; $i++) {
            $title       = fake()->sentence(rand(4, 8));
            $baseSlug    = \Illuminate\Support\Str::slug($title) . '-' . fake()->unique()->randomNumber(4);
            $publishedAt = fake()->dateTimeBetween('-2 years', 'now');

            $page = Page::create([
                'author_id'    => fake()->randomElement($userIds),
                'title'        => $title,
                'slug'         => $blogPrefix . '/' . $baseSlug,
                'type'         => 'post',
                'status'       => 'published',
                'published_at' => $publishedAt,
            ]);

            if ($textBlockType) {
                $paragraphs = rand(2, 4);
                $html       = '';
                for ($p = 0; $p < $paragraphs; $p++) {
                    $html .= '<p>' . fake()->paragraph() . '</p>';
                }

                PageWidget::create([
                    'page_id'        => $page->id,
                    'widget_type_id' => $textBlockType->id,
                    'config'         => ['content' => $html],
                    'sort_order'     => 0,
                ]);
            }

            if ($blogPagerType) {
                PageWidget::create([
                    'page_id'        => $page->id,
                    'widget_type_id' => $blogPagerType->id,
                    'config'         => [],
                    'sort_order'     => 1,
                ]);
            }
        }
    }

    public function wipe(): void
    {
        match ($this->type) {
            'events'     => $this->wipeEvents(),
            'products'   => $this->wipeProducts(),
            'blog_posts' => $this->wipeBlogPosts(),
            default      => $this->wipeGeneric(),
        };

        $this->feedback = "All {$this->type} deleted.";
    }

    private function wipeEvents(): void
    {
        EventRegistration::query()->delete();
        Event::query()->delete();
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
            'members'  => Membership::class,
        };

        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            $model::withTrashed()->forceDelete();
        } else {
            $model::query()->delete();
        }
    }
}
