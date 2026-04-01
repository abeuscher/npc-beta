<?php

use App\Filament\Resources\CollectionResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\DonationResource;
use App\Filament\Resources\EmailTemplateResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\FundResource;
use App\Filament\Resources\MembershipTierResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\WidgetTypeResource;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Fund;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Hard blocks ──────────────────────────────────────────────────────────────

it('prevents deletion of donations', function () {
    $donation = Donation::factory()->create();

    expect(DonationResource::canDelete($donation))->toBeFalse();
});

it('prevents deletion of email templates', function () {
    $template = EmailTemplate::create([
        'handle'  => 'test_template',
        'subject' => 'Test',
        'body'    => 'Test body',
    ]);

    expect(EmailTemplateResource::canDelete($template))->toBeFalse();
});

// ── Conditional blocks ───────────────────────────────────────────────────────

it('blocks event deletion when registrations exist', function () {
    $event = Event::factory()->create();
    EventRegistration::factory()->create(['event_id' => $event->id]);

    expect(EventResource::canDelete($event))->toBeFalse();
});

it('allows event deletion when no registrations exist', function () {
    $event = Event::factory()->create();

    expect(EventResource::canDelete($event))->toBeTrue();
});

it('blocks membership tier deletion when active memberships exist', function () {
    $tier = MembershipTier::factory()->create();
    $contact = Contact::factory()->create();
    Membership::factory()->create([
        'tier_id'    => $tier->id,
        'contact_id' => $contact->id,
        'status'     => 'active',
    ]);

    expect(MembershipTierResource::canDelete($tier))->toBeFalse();
});

it('allows membership tier deletion when no active memberships exist', function () {
    $tier = MembershipTier::factory()->create();
    $contact = Contact::factory()->create();
    Membership::factory()->create([
        'tier_id'    => $tier->id,
        'contact_id' => $contact->id,
        'status'     => 'expired',
    ]);

    expect(MembershipTierResource::canDelete($tier))->toBeTrue();
});

it('blocks fund deletion when donations reference it', function () {
    $fund = Fund::factory()->create();
    Donation::factory()->create(['fund_id' => $fund->id]);

    expect(FundResource::canDelete($fund))->toBeFalse();
});

it('allows fund deletion when no donations reference it', function () {
    $fund = Fund::factory()->create();

    expect(FundResource::canDelete($fund))->toBeTrue();
});

it('blocks product deletion when purchases exist', function () {
    $product = Product::factory()->create();
    $price = $product->prices()->create([
        'label'  => 'Default',
        'amount' => 10.00,
    ]);
    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
    ]);

    expect(ProductResource::canDelete($product))->toBeFalse();
});

it('allows product deletion when no purchases exist', function () {
    $product = Product::factory()->create();

    expect(ProductResource::canDelete($product))->toBeTrue();
});

it('blocks widget type deletion when page widgets reference it', function () {
    $widgetType = WidgetType::factory()->create();
    $page = Page::factory()->create();
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect(WidgetTypeResource::canDelete($widgetType))->toBeFalse();
});

it('allows widget type deletion when no page widgets reference it and not pinned', function () {
    $widgetType = WidgetType::factory()->create(['handle' => 'custom_test_widget']);

    expect(WidgetTypeResource::canDelete($widgetType))->toBeTrue();
});

// ── Contact force-delete restricted to super_admin ───────────────────────────

it('restricts contact force-delete to super_admin', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $regularAdmin = User::factory()->create();
    $regularAdmin->assignRole('admin');

    $contact = Contact::factory()->create();
    $contact->delete(); // soft-delete first

    $this->actingAs($superAdmin);
    expect(ContactResource::canForceDelete($contact))->toBeTrue();

    $this->actingAs($regularAdmin);
    expect(ContactResource::canForceDelete($contact))->toBeFalse();
});

// ── Page system type protection ──────────────────────────────────────────────

it('blocks deletion of system pages', function () {
    $systemPage = Page::factory()->create(['type' => 'system']);

    expect(PageResource::canDelete($systemPage))->toBeFalse();
});

it('allows deletion of regular pages with permission', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user);

    $page = Page::factory()->create(['type' => 'default']);

    expect(PageResource::canDelete($page))->toBeTrue();
});
