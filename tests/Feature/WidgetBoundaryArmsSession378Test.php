<?php

// Session 378 (Plugin Architecture arc P2): per-arm tests for the resolver
// arms added when the twelve model/auth/service-reaching widget templates
// were routed through declared data contracts. Every arm is fail-closed:
// only contract-declared fields appear in the DTO, and the PII-adjacent
// portal_member arm carries explicit non-leak coverage.

use App\Models\Fund;
use App\Models\MembershipTier;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PortalAccount;
use App\Models\User;
use App\Models\Contact;
use App\Models\Event;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function resolveArmContract(DataContract $contract): array
{
    return app(ContractResolver::class)
        ->resolve([$contract], new SlotContext(new PageAmbientContext()))[0];
}

// ── fund (list) ─────────────────────────────────────────────────────────────

it('fund arm returns active, non-archived funds in name order with only declared fields', function () {
    Fund::factory()->create(['name' => 'Zebra Fund', 'is_active' => true, 'is_archived' => false]);
    Fund::factory()->create(['name' => 'Alpha Fund', 'is_active' => true, 'is_archived' => false]);
    Fund::factory()->create(['name' => 'Hidden Inactive', 'is_active' => false, 'is_archived' => false]);
    Fund::factory()->create(['name' => 'Hidden Archived', 'is_active' => true, 'is_archived' => true]);

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['id', 'name'],
        model: 'fund',
    ));

    expect(array_column($dto['items'], 'name'))->toBe(['Alpha Fund', 'Zebra Fund'])
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['id', 'name']);
});

// ── membership_tier (list) ──────────────────────────────────────────────────

it('membership_tier arm returns active, non-archived tiers in sort order with only declared fields', function () {
    MembershipTier::factory()->create(['name' => 'Second', 'sort_order' => 2, 'is_active' => true, 'is_archived' => false, 'default_price' => 50]);
    MembershipTier::factory()->create(['name' => 'First', 'sort_order' => 1, 'is_active' => true, 'is_archived' => false, 'default_price' => null]);
    MembershipTier::factory()->create(['name' => 'Hidden Inactive', 'sort_order' => 0, 'is_active' => false, 'is_archived' => false]);
    MembershipTier::factory()->create(['name' => 'Hidden Archived', 'sort_order' => 0, 'is_active' => true, 'is_archived' => true]);

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['id', 'name', 'default_price', 'billing_interval'],
        model: 'membership_tier',
    ));

    expect(array_column($dto['items'], 'name'))->toBe(['First', 'Second'])
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['id', 'name', 'default_price', 'billing_interval'])
        ->and($dto['items'][0]['default_price'])->toBe('')
        ->and((float) $dto['items'][1]['default_price'])->toBe(50.0);
});

// ── navigation_menu (one) ───────────────────────────────────────────────────

function makeNavTreeMenu(): NavigationMenu
{
    $menu = NavigationMenu::create(['label' => 'Primary', 'handle' => 'primary-' . uniqid()]);
    $page = Page::factory()->create(['title' => 'Linked', 'slug' => 'nav-arm-linked', 'status' => 'published']);

    $parent = NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'label' => 'Parent', 'url' => null,
        'page_id' => $page->id, 'sort_order' => 0, 'target' => '_self', 'is_visible' => true,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'label' => 'Hidden', 'url' => '/hidden',
        'sort_order' => 1, 'target' => '_self', 'is_visible' => false,
    ]);
    $child = NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Child',
        'url' => '/child', 'sort_order' => 0, 'target' => '_blank', 'is_visible' => true,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'parent_id' => $child->id, 'label' => 'Grandchild',
        'url' => '/grandchild', 'sort_order' => 0, 'target' => '_self', 'is_visible' => true,
    ]);
    NavigationItem::create([
        'navigation_menu_id' => $menu->id, 'label' => 'Heading Only', 'url' => '#',
        'sort_order' => 2, 'target' => '_self', 'is_visible' => true,
    ]);

    return $menu;
}

it('navigation_menu arm projects the visible three-level tree with hrefs, targets, and link flags', function () {
    $menu = makeNavTreeMenu();

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['label', 'items', 'drop_fill_gradient_css'],
        filters: ['id' => (string) $menu->id, 'drop_fill_gradient' => null],
        model: 'navigation_menu',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    $item = $dto['item'];
    expect($item['label'])->toBe('Primary')
        ->and(array_column($item['items'], 'label'))->toBe(['Parent', 'Heading Only'])
        ->and($item['items'][0]['href'])->toBe(url('/nav-arm-linked'))
        ->and($item['items'][0]['is_link'])->toBeTrue()
        ->and($item['items'][1]['is_link'])->toBeFalse()
        ->and($item['items'][0]['children'][0]['label'])->toBe('Child')
        ->and($item['items'][0]['children'][0]['target'])->toBe('_blank')
        ->and($item['items'][0]['children'][0]['children'][0]['label'])->toBe('Grandchild')
        ->and(array_keys($item))->toEqualCanonicalizing(['label', 'items', 'drop_fill_gradient_css']);
});

it('navigation_menu arm yields a null item for a missing or blank menu id', function () {
    $blank = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['label', 'items'],
        filters: ['id' => ''],
        model: 'navigation_menu',
        cardinality: DataContract::CARDINALITY_ONE,
    ));
    $missing = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['label', 'items'],
        filters: ['id' => (string) \Illuminate\Support\Str::uuid()],
        model: 'navigation_menu',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($blank['item'])->toBeNull()
        ->and($missing['item'])->toBeNull();
});

it('navigation_menu arm precomputes the dropdown gradient css from contract filters', function () {
    $menu = makeNavTreeMenu();

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['drop_fill_gradient_css'],
        filters: [
            'id' => (string) $menu->id,
            'drop_fill_gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 90],
                ],
            ],
        ],
        model: 'navigation_menu',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($dto['item']['drop_fill_gradient_css'])->toContain('linear-gradient')
        ->and(array_keys($dto['item']))->toBe(['drop_fill_gradient_css']);
});

// ── portal_member (one) — the PII-sensitive arm ─────────────────────────────

function makePortalMember(array $contactAttrs = []): PortalAccount
{
    $contact = Contact::factory()->create(array_merge([
        'first_name'  => 'Pat',
        'last_name'   => 'Member',
        'city'        => 'Portland',
        'state'       => 'OR',
        'postal_code' => '97201',
        'country'     => 'USA',
    ], $contactAttrs));

    return PortalAccount::factory()->create(['contact_id' => $contact->id]);
}

it('portal_member arm yields a null item when nobody is logged in on the portal guard', function () {
    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['id'],
        model: 'portal_member',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($dto['item'])->toBeNull();
});

it('portal_member arm projects only contract-declared fields — undeclared PII never leaks', function () {
    $account = makePortalMember();
    $this->actingAs($account, 'portal');

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['first_name'],
        model: 'portal_member',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($dto['item'])->toBe(['first_name' => 'Pat'])
        ->and($dto['item'])->not->toHaveKey('email')
        ->and($dto['item'])->not->toHaveKey('city')
        ->and($dto['item'])->not->toHaveKey('postal_code')
        ->and($dto['item'])->not->toHaveKey('event_registrations');
});

it('portal_member arm projects declared contact prefill fields for the logged-in member', function () {
    $account = makePortalMember();
    $this->actingAs($account, 'portal');

    $dto = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['has_contact', 'email', 'city', 'state', 'postal_code', 'country'],
        model: 'portal_member',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($dto['item']['has_contact'])->toBeTrue()
        ->and($dto['item']['email'])->toBe($account->email)
        ->and($dto['item']['city'])->toBe('Portland')
        ->and($dto['item']['state'])->toBe('OR')
        ->and($dto['item']['postal_code'])->toBe('97201')
        ->and($dto['item']['country'])->toBe('USA');
});

it('portal_member arm projects the member event registrations only when declared', function () {
    $account = makePortalMember();
    $event = Event::factory()->create(['title' => 'Gala Night', 'status' => 'published']);
    $account->contact->eventRegistrations()->create([
        'event_id'      => $event->id,
        'name'          => 'Pat Member',
        'email'         => $account->email,
        'status'        => 'registered',
        'registered_at' => now(),
    ]);
    $this->actingAs($account, 'portal');

    $withRegs = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['has_contact', 'event_registrations'],
        model: 'portal_member',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($withRegs['item']['event_registrations'])->toHaveCount(1)
        ->and($withRegs['item']['event_registrations'][0]['event_title'])->toBe('Gala Night')
        ->and($withRegs['item']['event_registrations'][0]['status'])->toBe('registered')
        ->and(array_keys($withRegs['item']['event_registrations'][0]))->toEqualCanonicalizing(['event_title', 'event_date', 'status']);
});

// ── service arms: setup_checklist + scrub_counts (super-admin hard gate) ────

it('service arms yield a null item for logged-out visitors and non-super-admins', function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $checklist = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SERVICE,
        fields: ['is_first_run', 'items'],
        model: 'setup_checklist',
        cardinality: DataContract::CARDINALITY_ONE,
    );
    $counts = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SERVICE,
        fields: ['counts'],
        model: 'scrub_counts',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    expect(resolveArmContract($checklist)['item'])->toBeNull()
        ->and(resolveArmContract($counts)['item'])->toBeNull();

    $this->actingAs(User::factory()->create());

    expect(resolveArmContract($checklist)['item'])->toBeNull()
        ->and(resolveArmContract($counts)['item'])->toBeNull();
});

it('service arms project their datasets for a super-admin, filtered to declared fields', function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $checklist = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SERVICE,
        fields: ['is_first_run'],
        model: 'setup_checklist',
        cardinality: DataContract::CARDINALITY_ONE,
    ));
    $counts = resolveArmContract(new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SERVICE,
        fields: ['counts'],
        model: 'scrub_counts',
        cardinality: DataContract::CARDINALITY_ONE,
    ));

    expect($checklist['item'])->toBe(['is_first_run' => true])
        ->and($checklist['item'])->not->toHaveKey('items')
        ->and($counts['item']['counts'])->toBeArray()
        ->and($counts['item']['counts']['contacts'])->toBe(0);
});
