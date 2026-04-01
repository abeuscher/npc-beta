<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: skip if transactions already exist
        if (Transaction::exists()) {
            return;
        }

        // ── Contacts ─────────────────────────────────────────────────────────
        $contacts = Contact::factory()
            ->count(10)
            ->sequence(
                ['quickbooks_customer_id' => 'QB-100'],
                ['quickbooks_customer_id' => 'QB-101'],
                ['quickbooks_customer_id' => null],
                ['quickbooks_customer_id' => null],
                ['quickbooks_customer_id' => null],
                ['quickbooks_customer_id' => null],
                ['quickbooks_customer_id' => null],
                ['quickbooks_customer_id' => null],
                ['email' => null, 'quickbooks_customer_id' => null],
                ['email' => null, 'quickbooks_customer_id' => null],
            )
            ->create();

        // ── Donations ────────────────────────────────────────────────────────
        $donations = collect();

        // 4 one-off donations
        for ($i = 0; $i < 4; $i++) {
            $donations->push(Donation::factory()->create([
                'contact_id' => $contacts[$i]->id,
                'type'       => 'one_off',
                'amount'     => fake()->randomElement([25, 50, 100, 250, 500]),
            ]));
        }

        // 2 recurring donations
        for ($i = 4; $i < 6; $i++) {
            $donations->push(Donation::factory()->create([
                'contact_id' => $contacts[$i]->id,
                'type'       => 'recurring',
                'frequency'  => fake()->randomElement(['monthly', 'annual']),
                'amount'     => fake()->randomElement([25, 50, 100]),
            ]));
        }

        // ── Products & Purchases ─────────────────────────────────────────────
        $product = Product::factory()->create();
        $price   = ProductPrice::factory()->create([
            'product_id' => $product->id,
            'amount'     => 75.00,
        ]);

        $purchases = collect();
        for ($i = 6; $i < 9; $i++) {
            $purchases->push(Purchase::factory()->create([
                'product_id'       => $product->id,
                'product_price_id' => $price->id,
                'contact_id'       => $contacts[$i]->id,
                'amount_paid'      => 75.00,
                'status'           => 'active',
                'occurred_at'      => fake()->dateTimeBetween('-6 months', 'now'),
            ]));
        }

        // ── Transactions from donations ──────────────────────────────────────
        foreach ($donations as $index => $donation) {
            $synced = $index < 2; // First 2 already synced to QB

            Transaction::create([
                'subject_type'  => Donation::class,
                'subject_id'    => $donation->id,
                'contact_id'    => $donation->contact_id,
                'type'          => 'payment',
                'amount'        => $donation->amount,
                'direction'     => 'in',
                'status'        => 'completed',
                'stripe_id'     => 'ch_fake_' . fake()->unique()->regexify('[A-Za-z0-9]{14}'),
                'quickbooks_id' => $synced ? 'QBR-' . fake()->unique()->numerify('####') : null,
                'qb_synced_at'  => $synced ? now()->subDays(rand(1, 30)) : null,
                'occurred_at'   => $donation->started_at ?? now()->subDays(rand(1, 90)),
            ]);
        }

        // ── Transactions from purchases ──────────────────────────────────────
        foreach ($purchases as $purchase) {
            Transaction::create([
                'subject_type' => Purchase::class,
                'subject_id'   => $purchase->id,
                'contact_id'   => $purchase->contact_id,
                'type'         => 'payment',
                'amount'       => $purchase->amount_paid,
                'direction'    => 'in',
                'status'       => 'completed',
                'stripe_id'    => 'cs_fake_' . fake()->unique()->regexify('[A-Za-z0-9]{14}'),
                'occurred_at'  => $purchase->occurred_at,
            ]);
        }

        // ── Refund transactions ──────────────────────────────────────────────
        $refundDonation = $donations->first();

        Transaction::create([
            'subject_type' => Donation::class,
            'subject_id'   => $refundDonation->id,
            'contact_id'   => $refundDonation->contact_id,
            'type'         => 'refund',
            'amount'       => $refundDonation->amount,
            'direction'    => 'out',
            'status'       => 'completed',
            'stripe_id'    => 're_fake_' . fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'occurred_at'  => now()->subDays(rand(1, 14)),
        ]);

        Transaction::create([
            'subject_type' => Purchase::class,
            'subject_id'   => $purchases->first()->id,
            'contact_id'   => $purchases->first()->contact_id,
            'type'         => 'refund',
            'amount'       => $purchases->first()->amount_paid,
            'direction'    => 'out',
            'status'       => 'completed',
            'stripe_id'    => 're_fake_' . fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'occurred_at'  => now()->subDays(rand(1, 14)),
        ]);

        // ── Manual transactions ──────────────────────────────────────────────
        Transaction::create([
            'contact_id'  => $contacts[0]->id,
            'type'        => 'grant',
            'amount'      => 5000.00,
            'direction'   => 'in',
            'status'      => 'cleared',
            'occurred_at' => now()->subDays(rand(15, 60)),
        ]);

        Transaction::create([
            'contact_id'  => $contacts[3]->id,
            'type'        => 'adjustment',
            'amount'      => 150.00,
            'direction'   => 'in',
            'status'      => 'pending',
            'occurred_at' => now()->subDays(rand(1, 10)),
        ]);

        // ── Error state transaction ──────────────────────────────────────────
        Transaction::create([
            'subject_type'  => Donation::class,
            'subject_id'    => $donations->last()->id,
            'contact_id'    => $donations->last()->contact_id,
            'type'          => 'payment',
            'amount'        => $donations->last()->amount,
            'direction'     => 'in',
            'status'        => 'completed',
            'stripe_id'     => 'ch_fake_' . fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'qb_sync_error' => 'QuickBooks Sales Receipt creation failed: Business Validation Error — Deposit account not found.',
            'occurred_at'   => now()->subDays(rand(1, 30)),
        ]);
    }
}
