<?php

namespace App\Jobs;

use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Services\QuickBooks\QuickBooksAuth;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTransactionToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
    ) {}

    public function handle(): void
    {
        if (! app(QuickBooksAuth::class)->isConnected()) {
            return;
        }

        if (empty(SiteSetting::get('qb_income_account_id'))) {
            return;
        }

        if (filled($this->transaction->quickbooks_id)) {
            return;
        }

        try {
            $client = app(QuickBooksClient::class);

            $qbId = $this->transaction->direction === 'out'
                ? $client->createRefundReceipt($this->transaction)
                : $client->createSalesReceipt($this->transaction);

            $this->transaction->update([
                'quickbooks_id' => $qbId,
                'qb_synced_at' => now(),
                'qb_sync_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('QuickBooks sync failed', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = $e->getMessage();

            // Strip any token or credential data that might leak into the error
            $errorMessage = preg_replace(
                '/Bearer\s+\S+/i',
                'Bearer [REDACTED]',
                $errorMessage
            );

            $this->transaction->update([
                'qb_sync_error' => mb_substr($errorMessage, 0, 1000),
            ]);
        }
    }
}
