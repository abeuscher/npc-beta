<?php

namespace App\Filament\Pages;

use App\Filament\Actions\EmailPreviewWizardAction;
use App\Mail\DonationReceipt as DonationReceiptMail;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationReceipt;
use App\Models\EmailTemplate;
use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DonorsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Giving Summary';

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.donors';

    protected static ?string $title = 'Giving Summary';

    public string $taxYear         = '';
    public mixed $minimumTotal     = 250;
    public bool $includeBelowThreshold = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        return $user->hasRole('super_admin') || $user->can('manage_donations');
    }

    public function getBreadcrumbs(): array
    {
        return [
            'Finance',
            'Giving Summary',
        ];
    }

    public function mount(): void
    {
        $this->taxYear = (string) now()->year;
    }

    public function updatedTaxYear(): void
    {
        $this->resetTable();
    }

    public function updatedMinimumTotal(): void
    {
        if ($this->minimumTotal === '' || $this->minimumTotal === null) {
            $this->minimumTotal = 0;
        }
        $this->resetTable();
    }

    public function updatedIncludeBelowThreshold(): void
    {
        $this->resetTable();
    }

    public function getYearOptions(): array
    {
        $years = Donation::query()
            ->where('status', 'active')
            ->whereNotNull('started_at')
            ->selectRaw('EXTRACT(YEAR FROM started_at)::int AS year')
            ->distinct()
            ->orderByRaw('year DESC')
            ->pluck('year')
            ->map(fn ($y) => (string) $y)
            ->all();

        $current = (string) now()->year;
        if (! in_array($current, $years)) {
            array_unshift($years, $current);
        }

        $options = [];
        foreach ($years as $year) {
            $options[$year] = $year;
        }
        $options['all'] = 'All time';

        return $options;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->donorQuery())
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($q) use ($search) {
                        $q->where('contacts.first_name', 'ilike', "%{$search}%")
                          ->orWhere('contacts.last_name', 'ilike', "%{$search}%");
                    }))
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(
                        "COALESCE(contacts.last_name, contacts.first_name) {$direction}"
                    )),

                Tables\Columns\TextColumn::make('roles')
                    ->label('Roles')
                    ->getStateUsing(function (Contact $record): array {
                        $roles = [];
                        if ($record->memberships->isNotEmpty()) {
                            $roles[] = 'Member';
                        }
                        if ($record->donations->isNotEmpty()) {
                            $roles[] = 'Donor';
                        }
                        if (empty($roles)) {
                            $roles[] = 'Contact';
                        }
                        return $roles;
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Member'  => 'success',
                        'Donor'   => 'warning',
                        'Contact' => 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('donation_total')
                    ->label('Total Donated')
                    ->money('USD')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('donation_total', $direction))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('last_donation_at')
                    ->label('Last Donation')
                    ->date()
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('last_donation_at', $direction))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->getStateUsing(fn (Contact $record): string => collect([
                        $record->address_line_1,
                        $record->city,
                        $record->state,
                        $record->postal_code,
                    ])->filter()->implode(', '))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->defaultSort('donation_total', 'desc')
            ->emptyStateHeading('No donors found')
            ->emptyStateDescription('Try adjusting the year or threshold filters.');
    }

    protected function getHeaderActions(): array
    {
        return [
            EmailPreviewWizardAction::make(
                name: 'sendPending',
                emailTypeName: 'Donation Receipt',
                recipientSummary: function () {
                    $year     = $this->taxYear === 'all' ? null : (int) $this->taxYear;
                    $eligible = $this->eligibleContactIds($year);
                    $receipted = DonationReceipt::when($year, fn ($q) => $q->where('tax_year', $year))
                        ->whereIn('contact_id', $eligible)
                        ->distinct()
                        ->pluck('contact_id')
                        ->all();
                    $count = count(array_diff($eligible, $receipted));
                    $label = $this->taxYear === 'all' ? 'all years' : $this->taxYear;
                    return "<strong>{$count}</strong> donor(s) in <strong>{$label}</strong> have not yet received a receipt and will be emailed.";
                },
                previewHtmlResolver: fn () => $this->receiptPreviewHtml(forceAll: false),
                sendCallable: fn (array $data) => $this->sendReceipts(forceAll: false),
                submitLabel: 'Send Receipts',
                testHtmlResolver: fn () => $this->receiptTestHtml(),
            )
                ->label('Send System Emails to Pending Recipients')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->visible(fn () => $this->donorQuery()->exists()),

            EmailPreviewWizardAction::make(
                name: 'forceResendAll',
                emailTypeName: 'Donation Receipt (Re-send)',
                recipientSummary: function () {
                    $year  = $this->taxYear === 'all' ? null : (int) $this->taxYear;
                    $count = count($this->eligibleContactIds($year));
                    $label = $this->taxYear === 'all' ? 'all years' : $this->taxYear;
                    return "<strong>{$count}</strong> eligible donor(s) in <strong>{$label}</strong> will be re-emailed, including those already receipted. Each send creates a new receipt record.";
                },
                previewHtmlResolver: fn () => $this->receiptPreviewHtml(forceAll: true),
                sendCallable: fn (array $data) => $this->sendReceipts(forceAll: true),
                submitLabel: 'Re-send All',
                testHtmlResolver: fn () => $this->receiptTestHtml(),
            )
                ->label('Force Re-send System Emails to All')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->visible(fn () => $this->donorQuery()->exists()),
        ];
    }

    private function receiptPreviewHtml(bool $forceAll): string
    {
        $year     = $this->taxYear === 'all' ? null : (int) $this->taxYear;
        $eligible = $this->eligibleContactIds($year);

        if (! $forceAll) {
            $receipted = DonationReceipt::when($year, fn ($q) => $q->where('tax_year', $year))
                ->whereIn('contact_id', $eligible)
                ->distinct()
                ->pluck('contact_id')
                ->all();
            $targets = array_diff($eligible, $receipted);
        } else {
            $targets = $eligible;
        }

        $contactId = reset($targets);

        if (! $contactId) {
            return '<p style="font-family:sans-serif;padding:1em;">No eligible recipients found for the current filters.</p>';
        }

        $contact = Contact::find($contactId);

        if (! $contact) {
            return '<p style="font-family:sans-serif;padding:1em;">Unable to load first recipient.</p>';
        }

        $receiptYear             = $year ?? (int) now()->subYear()->year;
        [$breakdown, $total]     = $this->buildBreakdown($contactId, $receiptYear);
        $orgName                 = SiteSetting::get('site_name', '');

        $donationsHtml = '<table style="width:100%;border-collapse:collapse;margin:1em 0;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Fund</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Restriction</th>'
            . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ccc;">Amount</th>'
            . '</tr></thead><tbody>';

        foreach ($breakdown as $line) {
            $donationsHtml .= '<tr>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($line['fund_label']) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($line['restriction_type']) . '</td>'
                . '<td style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">$' . number_format((float) $line['amount'], 2) . '</td>'
                . '</tr>';
        }

        $donationsHtml .= '</tbody></table>';

        $tokens = [
            'contact_name' => $contact->display_name,
            'org_name'     => $orgName,
            'tax_year'     => (string) $receiptYear,
            'donations'    => $donationsHtml,
            'total'        => number_format((float) $total, 2),
        ];

        $template = EmailTemplate::forHandle('donation_receipt');

        return $template->resolveWrapper($template->render($tokens));
    }

    private function receiptTestHtml(): string
    {
        $year    = $this->taxYear === 'all' ? (int) now()->subYear()->year : (int) $this->taxYear;
        $orgName = SiteSetting::get('site_name', '');

        $donationsHtml = '<table style="width:100%;border-collapse:collapse;margin:1em 0;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Fund</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Restriction</th>'
            . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ccc;">Amount</th>'
            . '</tr></thead><tbody>'
            . '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">[Fund Name]</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">[Restriction Type]</td>'
            . '<td style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">$[0.00]</td>'
            . '</tr>'
            . '</tbody></table>';

        $tokens = [
            'contact_name' => '[Recipient Name]',
            'org_name'     => $orgName,
            'tax_year'     => (string) $year,
            'donations'    => $donationsHtml,
            'total'        => '0.00',
        ];

        $template = EmailTemplate::forHandle('donation_receipt');

        return $template->resolveWrapper($template->render($tokens));
    }

    public function sendReceipts(bool $forceAll): void
    {
        $year = $this->taxYear === 'all' ? null : (int) $this->taxYear;

        $eligible = $this->eligibleContactIds($year);

        if (! $forceAll) {
            $alreadyReceipied = DonationReceipt::when($year, fn ($q) => $q->where('tax_year', $year))
                ->whereIn('contact_id', $eligible)
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $targets = array_diff($eligible, $alreadyReceipied);
        } else {
            $targets = $eligible;
        }

        if (empty($targets)) {
            Notification::make()->title('No receipts to send')->warning()->send();
            return;
        }

        $sent = 0;

        foreach ($targets as $contactId) {
            $contact = Contact::find($contactId);
            if (! $contact || ! $contact->email) {
                continue;
            }

            $receiptYear = $year ?? (int) now()->subYear()->year;
            [$breakdown, $total] = $this->buildBreakdown($contactId, $receiptYear);

            $receipt = DonationReceipt::create([
                'contact_id'   => $contactId,
                'tax_year'     => $receiptYear,
                'sent_at'      => now(),
                'total_amount' => $total,
                'breakdown'    => $breakdown,
            ]);

            Mail::to($contact->email)->send(
                new DonationReceiptMail($contact, $receiptYear, $breakdown, number_format($total, 2))
            );

            ActivityLogger::log($receipt, 'receipt_sent', null, [
                'tax_year'     => $receiptYear,
                'total_amount' => $total,
                'resend'       => $forceAll,
            ]);

            $sent++;
        }

        Notification::make()
            ->title("Sent {$sent} receipt" . ($sent !== 1 ? 's' : ''))
            ->success()
            ->send();
    }

    private function donorQuery(): Builder
    {
        $year          = $this->taxYear;
        $threshold     = (float) $this->minimumTotal;
        $includeBelow  = $this->includeBelowThreshold;

        $donorStats = DB::table('donations')
            ->select('contact_id')
            ->selectRaw('SUM(amount) as donation_total')
            ->selectRaw('MAX(started_at) as last_donation_at')
            ->where('status', 'active')
            ->whereNotNull('contact_id');

        if ($year !== 'all') {
            $donorStats->whereYear('started_at', (int) $year);
        }

        $donorStats->groupBy('contact_id');

        return Contact::query()
            ->joinSub($donorStats, 'donor_stats', 'contacts.id', '=', 'donor_stats.contact_id')
            ->select('contacts.*', 'donor_stats.donation_total', 'donor_stats.last_donation_at')
            ->when(! $includeBelow && $threshold > 0,
                fn ($q) => $q->where('donor_stats.donation_total', '>=', $threshold)
            )
            ->with([
                'memberships' => fn ($q) => $q->where('status', 'active'),
                'donations',
            ]);
    }

    private function eligibleContactIds(?int $year): array
    {
        return Donation::query()
            ->where('status', 'active')
            ->when($year, fn ($q) => $q->whereYear('started_at', $year))
            ->whereNotNull('contact_id')
            ->distinct()
            ->pluck('contact_id')
            ->all();
    }

    private function buildBreakdown(string $contactId, int $year): array
    {
        $donations = Donation::query()
            ->where('contact_id', $contactId)
            ->where('status', 'active')
            ->whereYear('started_at', $year)
            ->with('fund')
            ->get();

        $groups = [];

        foreach ($donations as $donation) {
            $fundLabel       = $donation->fund?->name ?? 'General Fund';
            $restrictionType = $donation->fund?->restriction_type ?? 'unrestricted';
            $key             = $fundLabel;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'fund_label'       => $fundLabel,
                    'restriction_type' => $restrictionType,
                    'amount'           => 0,
                ];
            }

            $groups[$key]['amount'] += (float) $donation->amount;
        }

        $breakdown = array_values($groups);
        $total     = array_sum(array_column($breakdown, 'amount'));

        return [$breakdown, $total];
    }
}
