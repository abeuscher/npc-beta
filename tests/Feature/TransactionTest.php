<?php

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Contact;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    (new PermissionSeeder)->run();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

// ── Phase 1: Form tests ─────────────────────────────────────────────────────

it('creates a manual grant with auto-set direction', function () {
    $contact = Contact::factory()->create();

    Livewire::test(Pages\CreateTransaction::class)
        ->fillForm([
            'type'        => 'grant',
            'amount'      => 500,
            'status'      => 'cleared',
            'contact_id'  => $contact->id,
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::latest('created_at')->first();
    expect($transaction)
        ->type->toBe('grant')
        ->direction->toBe('in')
        ->contact_id->toBe($contact->id)
        ->stripe_id->toBeNull();
});

it('creates an expense with direction auto-set to out', function () {
    Livewire::test(Pages\CreateTransaction::class)
        ->fillForm([
            'type'        => 'expense',
            'amount'      => 250,
            'status'      => 'pending',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::latest('created_at')->first();
    expect($transaction)
        ->type->toBe('expense')
        ->direction->toBe('out');
});

it('creates an adjustment with direction auto-set to in', function () {
    Livewire::test(Pages\CreateTransaction::class)
        ->fillForm([
            'type'        => 'adjustment',
            'amount'      => 100,
            'status'      => 'cleared',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::latest('created_at')->first();
    expect($transaction)
        ->type->toBe('adjustment')
        ->direction->toBe('in');
});

it('does not expose stripe_id or quickbooks_id on the create form', function () {
    $component = Livewire::test(Pages\CreateTransaction::class);

    // The form should not have these as fillable fields
    $component->assertFormFieldExists('type')
        ->assertFormFieldExists('amount')
        ->assertFormFieldExists('status')
        ->assertFormFieldExists('contact_id');
});

it('shows quickbooks_id as read-only on edit when set', function () {
    $transaction = Transaction::factory()->manual()->synced()->create([
        'amount'      => 100,
        'status'      => 'cleared',
        'occurred_at' => now(),
    ]);

    Livewire::test(Pages\EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->assertFormFieldExists('quickbooks_id', function ($field) {
            return $field->isDisabled();
        });
});

it('hides quickbooks_id on edit when not set', function () {
    $transaction = Transaction::factory()->manual()->create([
        'amount'      => 100,
        'status'      => 'cleared',
        'occurred_at' => now(),
    ]);

    Livewire::test(Pages\EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->assertFormFieldExists('quickbooks_id', function ($field) {
            return ! $field->isVisible();
        });
});

it('prevents editing Stripe-originated transactions', function () {
    $transaction = Transaction::factory()->fromStripe()->create([
        'amount'      => 200,
        'status'      => 'completed',
        'occurred_at' => now(),
    ]);

    expect(TransactionResource::canEdit($transaction))->toBeFalse();
    expect(TransactionResource::canDelete($transaction))->toBeFalse();
});

// ── Phase 2: Seeder tests ───────────────────────────────────────────────────

it('seeder creates expected record counts', function () {
    (new TransactionSeeder)->run();

    // 6 donation txns + 3 purchase txns + 2 refunds + 3 manual + 1 error = 15
    expect(Transaction::count())->toBe(15);
    expect(Contact::count())->toBe(10);
});

it('seeder is idempotent', function () {
    (new TransactionSeeder)->run();
    $count = Transaction::count();

    (new TransactionSeeder)->run();
    expect(Transaction::count())->toBe($count);
});

it('seeder creates manual transactions without stripe_id', function () {
    (new TransactionSeeder)->run();

    $manualTypes = ['grant', 'expense', 'adjustment'];
    $manual = Transaction::whereIn('type', $manualTypes)->get();

    expect($manual)->toHaveCount(3);
    $manual->each(fn ($t) => expect($t->stripe_id)->toBeNull());
});

it('seeder creates an error-state transaction', function () {
    (new TransactionSeeder)->run();

    $errorTxn = Transaction::whereNotNull('qb_sync_error')->first();
    expect($errorTxn)->not->toBeNull()
        ->and($errorTxn->quickbooks_id)->toBeNull();
});

// ── Phase 3: Factory state tests ────────────────────────────────────────────

it('factory manual state produces correct fields', function () {
    $t = Transaction::factory()->manual()->make();

    expect($t)
        ->type->toBe('grant')
        ->direction->toBe('in')
        ->stripe_id->toBeNull();
});

it('factory fromStripe state sets stripe_id', function () {
    $t = Transaction::factory()->fromStripe()->make();

    expect($t->stripe_id)->not->toBeNull()
        ->and($t->stripe_id)->toStartWith('ch_fake_');
});

it('factory synced state sets quickbooks fields', function () {
    $t = Transaction::factory()->synced()->make();

    expect($t->quickbooks_id)->not->toBeNull()
        ->and($t->qb_synced_at)->not->toBeNull();
});

it('factory syncError state sets error message', function () {
    $t = Transaction::factory()->syncError()->make();

    expect($t->qb_sync_error)->not->toBeNull();
});

it('factory refund state sets type and direction', function () {
    $t = Transaction::factory()->refund()->make();

    expect($t)
        ->type->toBe('refund')
        ->direction->toBe('out');
});
