<?php

use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

// ── Session 302: Stripe-checkout branding relocated CMS Settings → Finance ──
// IA move only. Same SiteSetting keys, same values; only the page that hosts
// the section changed (and with it, the manage_financial_settings gate).

it('persists Stripe checkout branding from the Finance settings page using the same SiteSetting keys', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(FinanceSettingsPage::class)
        ->fillForm([
            'stripe_dashboard_branding_confirmed' => true,
            'stripe_checkout_submit_text'         => 'Thanks for supporting us.',
            'stripe_statement_descriptor'         => 'ACME FUND',
        ])
        ->call('save');

    expect(SiteSetting::get('stripe_dashboard_branding_confirmed'))->toBe('true')
        ->and(SiteSetting::get('stripe_checkout_submit_text'))->toBe('Thanks for supporting us.')
        ->and(SiteSetting::get('stripe_statement_descriptor'))->toBe('ACME FUND');
});

it('no longer exposes the Stripe branding fields on the renamed Site (CMS) settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(CmsSettingsPage::class)
        ->assertFormFieldDoesNotExist('stripe_checkout_submit_text')
        ->assertFormFieldDoesNotExist('stripe_dashboard_branding_confirmed')
        ->assertFormFieldDoesNotExist('stripe_statement_descriptor');
});
