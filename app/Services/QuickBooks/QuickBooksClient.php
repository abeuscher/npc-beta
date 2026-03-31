<?php

namespace App\Services\QuickBooks;

use App\Models\SiteSetting;
use App\Models\Transaction;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\RefundReceipt;
use QuickBooksOnline\API\Facades\SalesReceipt;
use RuntimeException;

class QuickBooksClient
{
    private DataService $dataService;

    private QuickBooksAuth $auth;

    public function __construct()
    {
        $this->auth = app(QuickBooksAuth::class);
    }

    public function createSalesReceipt(Transaction $transaction): string
    {
        $ds = $this->getDataService();
        $incomeAccountId = $this->getIncomeAccountId();

        $memo = $this->buildMemo($transaction);

        $receipt = SalesReceipt::create([
            'TxnDate' => $transaction->occurred_at->format('Y-m-d'),
            'PrivateNote' => $memo,
            'Line' => [[
                'Amount' => (float) $transaction->amount,
                'Description' => $memo,
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => [
                        'value' => '1',
                    ],
                    'Qty' => 1,
                    'UnitPrice' => (float) $transaction->amount,
                    'TaxCodeRef' => [
                        'value' => 'NON',
                    ],
                ],
            ]],
            'DepositToAccountRef' => [
                'value' => $incomeAccountId,
            ],
        ]);

        $result = $ds->Add($receipt);
        $error = $ds->getLastError();

        if ($error) {
            throw new RuntimeException(
                'QuickBooks Sales Receipt creation failed: ' . $error->getResponseBody()
            );
        }

        return (string) $result->Id;
    }

    public function createRefundReceipt(Transaction $transaction): string
    {
        $ds = $this->getDataService();
        $incomeAccountId = $this->getIncomeAccountId();

        $memo = $this->buildMemo($transaction);

        $receipt = RefundReceipt::create([
            'TxnDate' => $transaction->occurred_at->format('Y-m-d'),
            'PrivateNote' => $memo,
            'Line' => [[
                'Amount' => (float) $transaction->amount,
                'Description' => $memo,
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => [
                        'value' => '1',
                    ],
                    'Qty' => 1,
                    'UnitPrice' => (float) $transaction->amount,
                    'TaxCodeRef' => [
                        'value' => 'NON',
                    ],
                ],
            ]],
            'DepositToAccountRef' => [
                'value' => $incomeAccountId,
            ],
        ]);

        $result = $ds->Add($receipt);
        $error = $ds->getLastError();

        if ($error) {
            throw new RuntimeException(
                'QuickBooks Refund Receipt creation failed: ' . $error->getResponseBody()
            );
        }

        return (string) $result->Id;
    }

    /**
     * Fetch income-type accounts from QuickBooks Chart of Accounts.
     *
     * @return array<string, string> id => name
     */
    public function getIncomeAccounts(): array
    {
        $ds = $this->getDataService();

        $accounts = $ds->Query("SELECT * FROM Account WHERE AccountType = 'Income' MAXRESULTS 200");
        $error = $ds->getLastError();

        if ($error) {
            throw new RuntimeException(
                'QuickBooks account query failed: ' . $error->getResponseBody()
            );
        }

        $result = [];

        if ($accounts) {
            foreach ($accounts as $account) {
                $result[(string) $account->Id] = $account->Name;
            }
        }

        asort($result);

        return $result;
    }

    private function getDataService(): DataService
    {
        if (isset($this->dataService)) {
            return $this->dataService;
        }

        if (! $this->auth->refreshTokenIfNeeded()) {
            throw new RuntimeException(
                'QuickBooks is not connected or the token could not be refreshed.'
            );
        }

        $accessToken = SiteSetting::get('qb_access_token');
        $refreshToken = SiteSetting::get('qb_refresh_token');
        $realmId = $this->auth->getRealmId();
        $environment = env('QB_ENVIRONMENT', 'production');

        $this->dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => SiteSetting::get('qb_client_id', ''),
            'ClientSecret' => SiteSetting::get('qb_client_secret', ''),
            'accessTokenKey' => $accessToken,
            'refreshTokenKey' => $refreshToken,
            'QBORealmID' => $realmId,
            'baseUrl' => $environment === 'sandbox' ? 'development' : 'production',
        ]);

        $this->dataService->throwExceptionOnError(false);

        return $this->dataService;
    }

    private function getIncomeAccountId(): string
    {
        $accountId = SiteSetting::get('qb_income_account_id');

        if (empty($accountId)) {
            throw new RuntimeException(
                'No QuickBooks income account configured. Select one in Finance Settings.'
            );
        }

        return $accountId;
    }

    private function buildMemo(Transaction $transaction): string
    {
        $transaction->loadMissing('subject');

        $subject = $transaction->subject;
        $amount = '$' . number_format((float) $transaction->amount, 2);

        if ($subject instanceof \App\Models\Donation) {
            return "Donation — {$amount}";
        }

        if ($subject instanceof \App\Models\Purchase) {
            $subject->loadMissing('product');
            $productName = $subject->product?->name ?? 'Product';
            return "Product Purchase — {$productName} — {$amount}";
        }

        $type = ucfirst($transaction->type);
        return "{$type} — {$amount}";
    }
}
