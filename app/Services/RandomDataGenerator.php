<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SampleImage;
use App\Models\Transaction;
use App\Models\WidgetType;
use App\Services\SampleImageLibrary;
use App\WidgetPrimitive\Source;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class RandomDataGenerator
{
    public function scrubCounts(): array
    {
        return [
            'contacts'      => Contact::where('source', Source::SCRUB_DATA)->withTrashed()->count(),
            'events'        => Event::where('source', Source::SCRUB_DATA)->count(),
            'registrations' => EventRegistration::where('source', Source::SCRUB_DATA)->count(),
            'donations'     => Donation::where('source', Source::SCRUB_DATA)->count(),
            'memberships'   => Membership::where('source', Source::SCRUB_DATA)->withTrashed()->count(),
            'transactions'  => Transaction::where('source', Source::SCRUB_DATA)->count(),
            'posts'         => Page::where('source', Source::SCRUB_DATA)->where('type', 'post')->withTrashed()->count(),
            'products'      => Product::where('source', Source::SCRUB_DATA)->count(),
        ];
    }

    public function wipe(): array
    {
        if (! auth()->user()?->isSuperAdmin()) {
            throw new AuthorizationException('Random data wipe requires super admin role.');
        }

        return DB::transaction(function () {
            return [
                'transactions'  => Transaction::where('source', Source::SCRUB_DATA)->delete(),
                'registrations' => EventRegistration::where('source', Source::SCRUB_DATA)->delete(),
                'donations'     => Donation::where('source', Source::SCRUB_DATA)->delete(),
                'memberships'   => Membership::where('source', Source::SCRUB_DATA)->withTrashed()->forceDelete(),
                'events'        => Event::where('source', Source::SCRUB_DATA)->delete(),
                'posts'         => $this->wipeScrubPages(),
                'products'      => Product::where('source', Source::SCRUB_DATA)->delete(),
                'contacts'      => Contact::where('source', Source::SCRUB_DATA)->withTrashed()->forceDelete(),
            ];
        });
    }

    protected function wipeScrubPages(): int
    {
        $count = 0;
        foreach (Page::where('source', Source::SCRUB_DATA)->withTrashed()->get() as $page) {
            $page->forceDelete();
            $count++;
        }
        return $count;
    }

    public function seedWidgetCollections(): array
    {
        if (! auth()->user()?->isSuperAdmin()) {
            throw new AuthorizationException('Seeding widget demo collections requires super admin role.');
        }

        $labels = [];
        foreach (app(\App\Services\WidgetRegistry::class)->all() as $def) {
            $seederClass = $def->demoSeeder();
            if ($seederClass === null) {
                continue;
            }
            Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
            $labels[] = $def->label();
        }

        return $labels;
    }

    public function generate(array $counts): array
    {
        if (! auth()->user()?->isSuperAdmin()) {
            throw new AuthorizationException('Random data generation requires super admin role.');
        }

        return DB::transaction(function () use ($counts) {
            $summary = [
                'contacts'      => 0,
                'events'        => 0,
                'registrations' => 0,
                'donations'     => 0,
                'memberships'   => 0,
                'transactions'  => 0,
                'posts'         => 0,
                'products'      => 0,
            ];

            foreach (['contacts', 'events', 'registrations', 'donations', 'memberships', 'posts', 'products'] as $type) {
                $method = 'generate' . ucfirst($type);
                $result = $this->{$method}((int) ($counts[$type] ?? 0));
                foreach ($result as $key => $value) {
                    $summary[$key] = ($summary[$key] ?? 0) + $value;
                }
            }

            return $summary;
        });
    }

    protected function generateContacts(int $n): array
    {
        if ($n === 0) {
            return ['contacts' => 0];
        }

        $defs = CustomFieldDef::forModel('contact')->get();

        for ($i = 0; $i < $n; $i++) {
            Contact::factory()->create([
                'source'        => Source::SCRUB_DATA,
                'custom_fields' => $this->fakeCustomFields($defs),
            ]);
        }

        return ['contacts' => $n];
    }

    protected function generateEvents(int $n): array
    {
        if ($n === 0) {
            return ['events' => 0];
        }

        for ($i = 0; $i < $n; $i++) {
            $startsAt = now()
                ->addDays(rand(1, 30))
                ->setTime(rand(8, 20), [0, 15, 30, 45][rand(0, 3)]);
            $endsAt = (clone $startsAt)->addHours(rand(1, 4));

            $description = implode("\n\n", fake()->paragraphs(rand(3, 6)));

            $event = Event::factory()->create([
                'author_id'   => auth()->id(),
                'source'      => Source::SCRUB_DATA,
                'starts_at'   => $startsAt,
                'ends_at'     => $endsAt,
                'description' => $description,
            ]);

            $this->attachPoolImage($event, SampleImage::CATEGORY_STILL_PHOTOS, 'event_thumbnail');
            $this->attachPoolImage($event, SampleImage::CATEGORY_STILL_PHOTOS, 'event_header');
        }

        return ['events' => $n];
    }

    protected function generateRegistrations(int $n): array
    {
        if ($n === 0) {
            return ['registrations' => 0];
        }

        $eventIds   = Event::pluck('id')->all();
        $contactIds = Contact::pluck('id')->all();

        if (empty($eventIds)) {
            throw new \RuntimeException('Cannot generate event registrations: no events available. Generate events first or run again with events count > 0.');
        }

        EventRegistration::withoutEvents(function () use ($n, $eventIds, $contactIds) {
            for ($i = 0; $i < $n; $i++) {
                EventRegistration::factory()->create([
                    'event_id'   => fake()->randomElement($eventIds),
                    'contact_id' => ! empty($contactIds) && fake()->boolean(80) ? fake()->randomElement($contactIds) : null,
                    'source'     => Source::SCRUB_DATA,
                ]);
            }
        });

        return ['registrations' => $n];
    }

    protected function generateDonations(int $n): array
    {
        if ($n === 0) {
            return ['donations' => 0, 'transactions' => 0];
        }

        $contactIds = Contact::pluck('id')->all();

        if (empty($contactIds)) {
            throw new \RuntimeException('Cannot generate donations: no contacts available. Generate contacts first or run again with contacts count > 0.');
        }

        $transactionsCreated = 0;

        for ($i = 0; $i < $n; $i++) {
            $isActive = fake()->boolean(80);
            $contactId = fake()->randomElement($contactIds);

            $donation = Donation::factory()->create([
                'contact_id' => $contactId,
                'status'     => $isActive ? 'active' : 'pending',
                'source'     => Source::SCRUB_DATA,
            ]);

            if ($isActive) {
                Transaction::factory()->create([
                    'subject_type' => Donation::class,
                    'subject_id'   => $donation->id,
                    'contact_id'   => $contactId,
                    'amount'       => $donation->amount,
                    'source'       => Source::SCRUB_DATA,
                ]);
                $transactionsCreated++;
            }
        }

        return ['donations' => $n, 'transactions' => $transactionsCreated];
    }

    protected function generateMemberships(int $n): array
    {
        if ($n === 0) {
            return ['memberships' => 0, 'transactions' => 0];
        }

        $contactIds = Contact::pluck('id')->all();

        if (empty($contactIds)) {
            throw new \RuntimeException('Cannot generate memberships: no contacts available. Generate contacts first or run again with contacts count > 0.');
        }

        $tierIds             = MembershipTier::pluck('id')->all();
        $transactionsCreated = 0;

        for ($i = 0; $i < $n; $i++) {
            $isActive  = fake()->boolean(80);
            $contactId = fake()->randomElement($contactIds);

            $membership = Membership::factory()->create([
                'contact_id' => $contactId,
                'tier_id'    => empty($tierIds) ? null : fake()->randomElement($tierIds),
                'status'     => $isActive ? 'active' : 'pending',
                'source'     => Source::SCRUB_DATA,
            ]);

            if ($isActive && $membership->amount_paid > 0) {
                Transaction::factory()->create([
                    'subject_type' => Membership::class,
                    'subject_id'   => $membership->id,
                    'contact_id'   => $contactId,
                    'amount'       => $membership->amount_paid,
                    'source'       => Source::SCRUB_DATA,
                ]);
                $transactionsCreated++;
            }
        }

        return ['memberships' => $n, 'transactions' => $transactionsCreated];
    }

    protected function generatePosts(int $n): array
    {
        if ($n === 0) {
            return ['posts' => 0];
        }

        $blogPrefix    = config('site.blog_prefix', 'news');
        $textBlockType = WidgetType::where('handle', 'text_block')->first();
        $blogPagerType = WidgetType::where('handle', 'blog_pager')->first();

        for ($i = 0; $i < $n; $i++) {
            $title       = fake()->sentence(rand(4, 8));
            $baseSlug    = Str::slug($title) . '-' . fake()->unique()->randomNumber(4);
            $publishedAt = fake()->dateTimeBetween('-2 years', 'now');

            $page = Page::create([
                'author_id'    => auth()->id(),
                'title'        => $title,
                'slug'         => $blogPrefix . '/' . $baseSlug,
                'type'         => 'post',
                'status'       => 'published',
                'published_at' => $publishedAt,
                'source'       => Source::SCRUB_DATA,
            ]);

            if ($textBlockType) {
                $paragraphs = fake()->paragraphs(rand(10, 20));
                $content    = '<p>' . implode('</p><p>', $paragraphs) . '</p>';

                $page->widgets()->create([
                    'widget_type_id'    => $textBlockType->id,
                    'config'            => ['content' => $content],
                    'appearance_config' => PageWidget::resolveAppearance([], 'text_block'),
                    'sort_order'        => 0,
                ]);
            }

            if ($blogPagerType) {
                $page->widgets()->create([
                    'widget_type_id'    => $blogPagerType->id,
                    'config'            => [],
                    'appearance_config' => PageWidget::resolveAppearance([], 'blog_pager'),
                    'sort_order'        => 1,
                ]);
            }

            $this->attachPoolImage($page, SampleImage::CATEGORY_STILL_PHOTOS, 'post_thumbnail');
            $this->attachPoolImage($page, SampleImage::CATEGORY_STILL_PHOTOS, 'post_header');
        }

        return ['posts' => $n];
    }

    protected function generateProducts(int $n): array
    {
        if ($n === 0) {
            return ['products' => 0];
        }

        for ($i = 0; $i < $n; $i++) {
            $product = Product::factory()->create([
                'source'     => Source::SCRUB_DATA,
                'sort_order' => $i + 1,
            ]);

            $priceCount = rand(1, 3);
            for ($j = 0; $j < $priceCount; $j++) {
                ProductPrice::factory()->create([
                    'product_id' => $product->id,
                    'sort_order' => $j + 1,
                ]);
            }

            $this->attachPoolImage($product, SampleImage::CATEGORY_PRODUCT_PHOTOS, 'product_image');
        }

        return ['products' => $n];
    }

    protected function attachPoolImage(HasMedia $model, string $category, string $collection): void
    {
        $media = app(SampleImageLibrary::class)->random($category, 1)->first();
        if ($media === null) {
            return;
        }

        try {
            $model->addMedia($media->getPath())
                ->preservingOriginal()
                ->toMediaCollection($collection);
        } catch (\Throwable $e) {
            // Silent: pool attachment is a demo nicety, not a correctness requirement.
        }
    }

    protected function fakeCustomFields(Collection $defs): array
    {
        $result = [];

        foreach ($defs as $def) {
            $result[$def->handle] = match ($def->field_type) {
                'number'    => fake()->numberBetween(1, 100),
                'date'      => fake()->date(),
                'boolean'   => fake()->boolean(),
                'select'    => $this->randomSelectOption($def),
                'rich_text' => '<p>' . fake()->paragraph() . '</p>',
                default     => fake()->sentence(3),
            };
        }

        return $result;
    }

    protected function randomSelectOption(CustomFieldDef $def): ?string
    {
        if (empty($def->options)) {
            return null;
        }

        $option = fake()->randomElement($def->options);

        if (is_array($option)) {
            return $option['value'] ?? null;
        }

        return (string) $option;
    }
}
