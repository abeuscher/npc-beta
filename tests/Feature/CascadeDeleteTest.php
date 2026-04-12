<?php

use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use App\Models\Donation;
use App\Models\DonationReceipt;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\FormSubmission;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WidgetType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Contact force-delete: RESTRICT paths ────────────────────────────────────

it('blocks contact force-delete when donation receipts exist', function () {
    $contact = Contact::factory()->create();
    DonationReceipt::factory()->create(['contact_id' => $contact->id]);

    expect(fn () => $contact->forceDelete())->toThrow(QueryException::class);
});

it('blocks contact force-delete when memberships exist', function () {
    $contact = Contact::factory()->create();
    Membership::factory()->create(['contact_id' => $contact->id]);

    expect(fn () => $contact->forceDelete())->toThrow(QueryException::class);
});

// ── Contact force-delete: SET NULL paths ────────────────────────────────────

it('nulls donation contact_id on contact force-delete', function () {
    $contact = Contact::factory()->create();
    $donation = Donation::factory()->create(['contact_id' => $contact->id]);

    $contact->forceDelete();

    $this->assertDatabaseHas('donations', ['id' => $donation->id, 'contact_id' => null]);
});

it('nulls event_registration contact_id on contact force-delete', function () {
    $contact = Contact::factory()->create();
    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create([
        'contact_id' => $contact->id,
        'event_id'   => $event->id,
    ]);

    $contact->forceDelete();

    $this->assertDatabaseHas('event_registrations', ['id' => $registration->id, 'contact_id' => null]);
});

it('nulls transaction contact_id on contact force-delete', function () {
    $contact = Contact::factory()->create();
    $transaction = Transaction::factory()->create(['contact_id' => $contact->id]);

    $contact->forceDelete();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'contact_id' => null]);
});

// ── Contact soft-delete does NOT cascade ────────────────────────────────────

it('preserves memberships when contact is soft-deleted', function () {
    $contact = Contact::factory()->create();
    $membership = Membership::factory()->create(['contact_id' => $contact->id]);

    $contact->delete();

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    $this->assertDatabaseHas('memberships', ['id' => $membership->id, 'contact_id' => $contact->id]);
});

it('preserves donation receipts when contact is soft-deleted', function () {
    $contact = Contact::factory()->create();
    $receipt = DonationReceipt::factory()->create(['contact_id' => $contact->id]);

    $contact->delete();

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    $this->assertDatabaseHas('donation_receipts', ['id' => $receipt->id, 'contact_id' => $contact->id]);
});

// ── User delete: RESTRICT on authored content ───────────────────────────────

it('blocks user delete when pages are authored by them', function () {
    $user = User::factory()->create();
    Page::factory()->create(['author_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('blocks user delete when events are authored by them', function () {
    $user = User::factory()->create();
    Event::factory()->create(['author_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

// ── User delete: SET NULL paths ─────────────────────────────────────────────

it('nulls import_sessions imported_by on user delete', function () {
    $user = User::factory()->create();
    $source = ImportSource::create(['name' => 'Test Source']);
    $session = ImportSession::create([
        'import_source_id' => $source->id,
        'model_type'       => 'contact',
        'status'           => 'approved',
        'imported_by'      => $user->id,
    ]);

    $user->forceDelete();

    $this->assertDatabaseHas('import_sessions', ['id' => $session->id, 'imported_by' => null]);
});

it('nulls contact_duplicate_dismissals dismissed_by on user delete', function () {
    $user = User::factory()->create();
    $contactA = Contact::factory()->create();
    $contactB = Contact::factory()->create();

    $aId = min($contactA->id, $contactB->id);
    $bId = max($contactA->id, $contactB->id);

    $dismissal = ContactDuplicateDismissal::create([
        'contact_id_a' => $aId,
        'contact_id_b' => $bId,
        'dismissed_by' => $user->id,
        'dismissed_at' => now(),
    ]);

    $user->forceDelete();

    $this->assertDatabaseHas('contact_duplicate_dismissals', ['id' => $dismissal->id, 'dismissed_by' => null]);
});

// ── Widget type delete: RESTRICT ────────────────────────────────────────────

it('blocks widget type delete when page widgets reference it', function () {
    $widgetType = WidgetType::factory()->create();
    $page = Page::factory()->create();
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => [],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect(fn () => $widgetType->delete())->toThrow(QueryException::class);
});

// ── Product delete: RESTRICT via purchases ──────────────────────────────────

it('blocks product delete when purchases exist', function () {
    $product = Product::factory()->create();
    $price = ProductPrice::factory()->create(['product_id' => $product->id]);
    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
    ]);

    expect(fn () => $product->delete())->toThrow(QueryException::class);
});

// ── Product delete: CASCADE to waitlist entries ─────────────────────────────

it('cascades waitlist entry delete when product is deleted', function () {
    $product = Product::factory()->create();
    $entry = WaitlistEntry::factory()->create(['product_id' => $product->id]);

    // Remove any purchases/allocations that would block delete
    $product->delete();

    $this->assertDatabaseMissing('waitlist_entries', ['id' => $entry->id]);
});

// ── Page force-delete: CASCADE to widgets ───────────────────────────────────

it('cascades page widget delete when page is force-deleted', function () {
    $page = Page::factory()->create();
    $widgetType = WidgetType::factory()->create();
    $widget = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => [],
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $page->forceDelete();

    $this->assertDatabaseMissing('page_widgets', ['id' => $widget->id]);
});

// ── Event delete: CASCADE to registrations ──────────────────────────────────

it('cascades registration delete when event is deleted', function () {
    $event = Event::factory()->create();
    $reg = EventRegistration::factory()->create(['event_id' => $event->id]);

    $event->delete();

    $this->assertDatabaseMissing('event_registrations', ['id' => $reg->id]);
});
