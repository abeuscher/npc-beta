<?php

namespace App\Services\QuickBooks;

use App\Models\Contact;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\Customer;
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

    public function createSalesReceipt(Transaction $transaction, ?string $qbCustomerId = null): string
    {
        $ds = $this->getDataService();
        $incomeAccountId = $this->getIncomeAccountId();

        $memo = $this->buildMemo($transaction);

        $data = [
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
        ];

        if ($qbCustomerId) {
            $data['CustomerRef'] = ['value' => $qbCustomerId];
        }

        $receipt = SalesReceipt::create($data);

        $result = $ds->Add($receipt);
        $error = $ds->getLastError();

        if ($error) {
            throw new RuntimeException(
                'QuickBooks Sales Receipt creation failed: ' . $error->getResponseBody()
            );
        }

        return (string) $result->Id;
    }

    public function createRefundReceipt(Transaction $transaction, ?string $qbCustomerId = null): string
    {
        $ds = $this->getDataService();
        $incomeAccountId = $this->getIncomeAccountId();

        $memo = $this->buildMemo($transaction);

        $data = [
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
        ];

        if ($qbCustomerId) {
            $data['CustomerRef'] = ['value' => $qbCustomerId];
        }

        $receipt = RefundReceipt::create($data);

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
        Log::info('QB getIncomeAccounts: starting');

        $ds = $this->getDataService();
        Log::info('QB getIncomeAccounts: DataService ready');

        // Diagnostic: query ALL accounts to see what QB returns
        $allAccounts = $ds->Query("SELECT * FROM Account MAXRESULTS 200");
        $allError = $ds->getLastError();

        if ($allError) {
            Log::error('QB getIncomeAccounts: all-accounts query failed', [
                'error' => $allError->getResponseBody(),
            ]);
        } else {
            $types = [];
            if ($allAccounts) {
                foreach ($allAccounts as $a) {
                    $types[] = [
                        'Id' => (string) $a->Id,
                        'Name' => $a->Name,
                        'AccountType' => $a->AccountType,
                        'AccountSubType' => $a->AccountSubType ?? null,
                        'Active' => $a->Active ?? true,
                    ];
                }
            }
            Log::info('QB getIncomeAccounts: all accounts returned', ['count' => count($types), 'accounts' => $types]);
        }

        // Filtered query for Income type only
        $accounts = $ds->Query("SELECT * FROM Account WHERE AccountType = 'Income' MAXRESULTS 200");
        $error = $ds->getLastError();

        if ($error) {
            Log::error('QB getIncomeAccounts: income query failed', [
                'error' => $error->getResponseBody(),
            ]);
            throw new RuntimeException(
                'QuickBooks account query failed: ' . $error->getResponseBody()
            );
        }

        Log::info('QB getIncomeAccounts: income query returned', [
            'count' => $accounts ? count($accounts) : 0,
        ]);

        $result = [];

        if ($accounts) {
            foreach ($accounts as $account) {
                $result[(string) $account->Id] = $account->Name;
            }
        }

        asort($result);

        return $result;
    }

    public function findOrCreateCustomer(Contact $contact): ?string
    {
        if (empty($contact->email)) {
            return null;
        }

        // Return cached ID if already linked
        if (filled($contact->quickbooks_customer_id)) {
            return $contact->quickbooks_customer_id;
        }

        $ds = $this->getDataService();

        // Query QB for existing customer by email
        $email = str_replace("'", "\\'", $contact->email);
        $customers = $ds->Query("SELECT * FROM Customer WHERE PrimaryEmailAddr = '{$email}' MAXRESULTS 10");
        $error = $ds->getLastError();

        if ($error) {
            throw new RuntimeException(
                'QuickBooks Customer query failed: ' . $error->getResponseBody()
            );
        }

        // Use first active match if found
        if ($customers) {
            foreach ($customers as $customer) {
                if (($customer->Active ?? true) !== false) {
                    $qbCustomerId = (string) $customer->Id;

                    if (count($customers) > 1) {
                        Log::warning('QuickBooks: multiple customers found for email, using first active match', [
                            'contact_id' => $contact->id,
                            'email' => $contact->email,
                            'qb_customer_id' => $qbCustomerId,
                        ]);
                    }

                    $contact->update(['quickbooks_customer_id' => $qbCustomerId]);

                    return $qbCustomerId;
                }
            }
        }

        // No match — create a new QB Customer
        $displayName = trim("{$contact->first_name} {$contact->last_name}");
        if (empty($displayName)) {
            $displayName = $contact->email;
        }

        $qbCustomerId = $this->createQbCustomer($ds, $contact, $displayName);

        $contact->update(['quickbooks_customer_id' => $qbCustomerId]);

        return $qbCustomerId;
    }

    private function createQbCustomer(DataService $ds, Contact $contact, string $displayName): string
    {
        $customerData = [
            'DisplayName' => $displayName,
            'GivenName' => $contact->first_name ?? '',
            'FamilyName' => $contact->last_name ?? '',
            'PrimaryEmailAddr' => [
                'Address' => $contact->email,
            ],
        ];

        $customer = Customer::create($customerData);
        $result = $ds->Add($customer);
        $error = $ds->getLastError();

        if ($error) {
            $body = $error->getResponseBody();

            // Handle DisplayName uniqueness collision — retry with email suffix
            if (str_contains($body, 'Duplicate Name Exists') || str_contains($body, 'already been used')) {
                $customerData['DisplayName'] = "{$displayName} ({$contact->email})";
                $customer = Customer::create($customerData);
                $result = $ds->Add($customer);
                $error = $ds->getLastError();

                if ($error) {
                    throw new RuntimeException(
                        'QuickBooks Customer creation failed on retry: ' . $error->getResponseBody()
                    );
                }

                return (string) $result->Id;
            }

            throw new RuntimeException(
                'QuickBooks Customer creation failed: ' . $body
            );
        }

        return (string) $result->Id;
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
