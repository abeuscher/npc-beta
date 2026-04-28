<?php

namespace Database\Factories;

use App\WidgetPrimitive\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => null,
            'subject_id'   => null,
            'type'         => 'payment',
            'amount'       => $this->faker->randomFloat(2, 10, 5000),
            'direction'    => 'in',
            'status'       => 'completed',
            'occurred_at'  => now(),
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'type'      => 'grant',
            'direction' => 'in',
            'stripe_id' => null,
        ]);
    }

    public function fromStripe(): static
    {
        return $this->state(fn () => [
            'source'    => Source::STRIPE_WEBHOOK,
            'stripe_id' => 'ch_fake_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }

    public function synced(): static
    {
        return $this->state(fn () => [
            'quickbooks_id' => 'QBR-' . $this->faker->unique()->numerify('####'),
            'qb_synced_at'  => now(),
        ]);
    }

    public function syncError(): static
    {
        return $this->state(fn () => [
            'qb_sync_error' => 'QuickBooks Sales Receipt creation failed: Business Validation Error — Deposit account not found.',
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'type'      => 'refund',
            'direction' => 'out',
        ]);
    }
}
