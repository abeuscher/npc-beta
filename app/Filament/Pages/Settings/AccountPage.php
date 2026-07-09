<?php

namespace App\Filament\Pages\Settings;

use App\Services\Billing\BillingState;
use App\Services\Billing\BillingStateReader;
use App\Support\DateFormat;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The node's read-only "My Account" page (client billing, CB2 / session 367).
 *
 * Renders EXCLUSIVELY from the billing-state document Fleet Manager pushes to the
 * node (contract v2.6.0), read through BillingStateReader / BillingState. It holds
 * no vendor-Stripe credential, config key, or SDK call — every "editable" part is
 * a hand-off to Stripe's hosted billing portal (portalUrl), never a local form.
 * The word "Stripe" appears in this code only as the label on that portal link;
 * the convention-drift guard (ConventionDriftTest) makes that discipline
 * mechanical.
 *
 * Self-hiding: canAccess() requires BOTH the manage_account ability AND a present
 * billing-state document, so on internal nodes / fresh installs (no document
 * pushed) the page is absent from navigation and returns 403 on direct URL —
 * additive by construction, exactly as CB1 is inert until FM pushes.
 */
class AccountPage extends Page
{
    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Account';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.settings.account-page';

    protected static ?string $title = 'Account';

    /** Currency-symbol map for the common billing currencies; else the ISO code. */
    private const CURRENCY_SYMBOLS = [
        'usd' => '$', 'eur' => '€', 'gbp' => '£',
        'cad' => 'CA$', 'aud' => 'A$', 'nzd' => 'NZ$',
    ];

    /** Baked, display-ready view-model, built in mount() from the pushed document. */
    public array $account = [];

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('manage_account') ?? false)
            && app(BillingStateReader::class)->read()->isPresent();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Self-hide from the sidebar whenever the page is inaccessible — no
        // manage_account ability, or no billing-state document on this node.
        return static::canAccess();
    }

    public function getHeading(): string
    {
        return 'My Account';
    }

    public function getBreadcrumbs(): array
    {
        return [url()->current() => 'Account'];
    }

    public function mount(): void
    {
        // canAccess() has already guaranteed a present document by the time we
        // mount; read it once (per-request singleton) and bake the view-model.
        $this->account = $this->buildViewModel(app(BillingStateReader::class)->read());
    }

    // ── View-model assembly ──────────────────────────────────────────────────

    private function buildViewModel(BillingState $state): array
    {
        return [
            'status'              => $this->statusBadge($state),
            'plan'                => $this->planLine($state),
            'nextInvoice'         => $this->nextInvoiceLine($state),
            'billingContactEmail' => $state->billingContactEmail(),
            'portalUrl'           => $state->portalUrl(),
            'asOf'                => $this->asOfLine($state),
            'attention'           => $state->needsBillingAttention() ? $this->attentionLine($state) : null,
        ];
    }

    /** Plain-English subscription status + a Filament badge colour. */
    private function statusBadge(BillingState $state): array
    {
        return match ($state->status()) {
            'active'             => ['label' => 'Active', 'color' => 'success'],
            'past_due', 'unpaid' => ['label' => 'Payment problem — please update your card', 'color' => 'danger'],
            'trialing'           => ['label' => $this->trialLabel($state), 'color' => 'warning'],
            'canceled'           => ['label' => 'Canceled', 'color' => 'gray'],
            null                 => ['label' => 'Unknown', 'color' => 'gray'],
            default              => ['label' => Str::headline((string) $state->status()), 'color' => 'gray'],
        };
    }

    private function trialLabel(BillingState $state): string
    {
        $endsAt = $state->trial()['ends_at'] ?? null;

        if (! is_string($endsAt) || $endsAt === '') {
            return 'Trial';
        }

        try {
            $ends = Carbon::parse($endsAt);
        } catch (\Throwable) {
            return 'Trial';
        }

        if ($ends->isPast()) {
            return 'Trial ended';
        }

        $days = (int) Carbon::now()->startOfDay()->diffInDays($ends->startOfDay());

        return $days === 0
            ? 'Trial — ends today'
            : 'Trial — ' . $days . ' ' . Str::plural('day', $days) . ' left';
    }

    private function planLine(BillingState $state): ?array
    {
        $plan = $state->plan();

        if ($plan === null) {
            return null;
        }

        $price = $this->formatMoney($plan['amount'] ?? null, $plan['currency'] ?? null);
        $interval = is_string($plan['interval'] ?? null) ? $plan['interval'] : null;

        return [
            'name'  => is_string($plan['name'] ?? null) ? $plan['name'] : null,
            'price' => $price !== null && $interval !== null ? $price . ' / ' . $interval : $price,
        ];
    }

    private function nextInvoiceLine(BillingState $state): ?array
    {
        $invoice = $state->nextInvoice();

        if ($invoice === null) {
            return null;
        }

        // Invoice amounts share the plan's currency (the schema carries currency
        // only on the plan object; a client's invoice is billed in that currency).
        $currency = $state->plan()['currency'] ?? null;

        $lineItems = [];
        foreach ((array) ($invoice['line_items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $lineItems[] = [
                'description' => is_string($item['description'] ?? null) ? $item['description'] : '',
                'amount'      => $this->formatMoney($item['amount'] ?? null, $currency),
            ];
        }

        return [
            'date'      => is_string($invoice['date'] ?? null) ? $this->formatDate($invoice['date']) : null,
            'amount'    => $this->formatMoney($invoice['amount'] ?? null, $currency),
            'lineItems' => $lineItems,
        ];
    }

    private function asOfLine(BillingState $state): ?array
    {
        $asOf = $state->asOf();

        if ($asOf === null) {
            return null;
        }

        try {
            $ts = Carbon::parse($asOf);
        } catch (\Throwable) {
            return null;
        }

        return [
            'relative' => $ts->diffForHumans(),
            'exact'    => DateFormat::format($ts, DateFormat::MEDIUM_DATETIME),
        ];
    }

    private function attentionLine(BillingState $state): array
    {
        $graceEnds = $state->graceEndsAt();
        $locksAt = null;

        if (is_string($graceEnds)) {
            try {
                $locksAt = DateFormat::format(Carbon::parse($graceEnds), DateFormat::LONG_DATE);
            } catch (\Throwable) {
                $locksAt = null;
            }
        }

        return ['locksAt' => $locksAt];
    }

    /** Format Stripe minor units (cents) + a currency code into a display string. */
    private function formatMoney(mixed $minor, mixed $currency): ?string
    {
        if (! is_numeric($minor)) {
            return null;
        }

        $amount = number_format(((int) $minor) / 100, 2);
        $code = is_string($currency) ? strtolower($currency) : '';
        $symbol = self::CURRENCY_SYMBOLS[$code] ?? null;

        return $symbol !== null ? $symbol . $amount : trim($amount . ' ' . strtoupper($code));
    }

    private function formatDate(string $iso): ?string
    {
        try {
            return DateFormat::format(Carbon::parse($iso), DateFormat::MEDIUM_DATE);
        } catch (\Throwable) {
            return null;
        }
    }
}
