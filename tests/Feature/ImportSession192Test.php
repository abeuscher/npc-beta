<?php

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Services\Import\DuplicateHeaderDetector;

function makeWizardHarness(): object
{
    return new class {
        use InteractsWithImportWizard;

        public array  $parsedHeaders     = [];
        public string $uploadedFilePath  = '';
        public array  $previewRows       = [];
        public array  $sampleRows        = [];
        public string $importSessionId   = '';
        public string $resolvedSourceId  = '';
        public string $pendingSourceName = '';
        public string $savedSourceName   = '';
        public bool   $usedSavedMapping  = false;
        public array  $autoCustomLog     = [];
        public array  $duplicateFindings = [];
        public ?array $data              = [];

        public function callSeed(): void
        {
            $this->seedReviewDecisionDefaults();
        }

        public function callFinalize(): void
        {
            $this->finalizeReviewDecisions();
        }

        public function callSample(int $i): array
        {
            return $this->sampleValuesFor($i);
        }
    };
}

// ─── DuplicateHeaderDetector — empty input ───────────────────────────────

it('returns empty findings for an empty header array', function () {
    expect(DuplicateHeaderDetector::detect([]))->toBe([]);
});

it('returns empty findings when no duplicates or similarities exist', function () {
    $headers = ['First Name', 'Last Name', 'Email', 'City'];

    expect(DuplicateHeaderDetector::detect($headers))->toBe([]);
});

// ─── Rule 1: exact match after normalization ─────────────────────────────

it('groups headers whose normalized forms match exactly', function () {
    $headers  = ['First name', 'FirstName', 'first_name', 'Email'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('exact_match_normalized');
    expect($findings[0]['headers'])->toBe(['First name', 'FirstName', 'first_name']);
    expect($findings[0]['indices'])->toBe([0, 1, 2]);
});

it('is case- and whitespace-insensitive in exact matches', function () {
    $headers  = ['  EMAIL  ', 'e-mail', 'Email'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('exact_match_normalized');
    expect($findings[0]['headers'])->toHaveCount(3);
});

// ─── Compound-header non-grouping (prefix-subset intentionally omitted) ──

it('does not group compound line-item headers sharing a prefix word', function () {
    $headers  = ['Item', 'Item quantity', 'Item price', 'Item amount'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toBe([]);
});

it('does not group single-word header with a two-word header sharing its prefix', function () {
    $headers  = ['Email', 'Email address', 'Phone', 'Phone number'];
    $findings = DuplicateHeaderDetector::detect($headers);

    // Downstream mapping-step collision detection is responsible for these.
    expect($findings)->toBe([]);
});

// ─── Rule 2: trailing digit / suffix ─────────────────────────────────────

it('groups headers that differ only by a trailing number', function () {
    $headers  = ['Nickname', 'Nickname 2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('trailing_digit_suffix');
    expect($findings[0]['headers'])->toBe(['Nickname', 'Nickname 2']);
});

it('does not group distinct headers even when both have short base words', function () {
    $headers  = ['City', 'Town'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toBe([]);
});

it('groups underscore-separated trailing numbers', function () {
    $headers  = ['Name_1', 'Name_2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('trailing_digit_suffix');
});

it('groups parenthesized suffix variants', function () {
    $headers  = ['Email', 'Email (alt)'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('trailing_digit_suffix');
});

// ─── Rule priority / dedup ───────────────────────────────────────────────

it('prefers higher-priority rules when a header qualifies for multiple', function () {
    $headers  = ['First name', 'FirstName', 'first_name', 'Nickname', 'Nickname 2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(2);

    $rules = array_column($findings, 'rule');
    expect($rules)->toContain('exact_match_normalized');
    expect($rules)->toContain('trailing_digit_suffix');
});

// ─── Carve-outs: address-line family ─────────────────────────────────────

it('does not flag Address / Address 1 / Address 2 as duplicates', function () {
    $headers  = ['Address', 'Address 1', 'Address 2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toBe([]);
});

it('does not flag Address / Address Line 1 / Address Line 2 as duplicates', function () {
    $headers  = ['Address', 'Address Line 1', 'Address Line 2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toBe([]);
});

it('does not flag Street / Street Address / Street Address 2 as duplicates', function () {
    $headers  = ['Street', 'Street Address', 'Street Address 2'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toBe([]);
});

it('still flags duplicates when address-like headers appear alongside true duplicates', function () {
    $headers  = ['Address', 'Address 2', 'Email', 'email'];
    $findings = DuplicateHeaderDetector::detect($headers);

    expect($findings)->toHaveCount(1);
    expect($findings[0]['rule'])->toBe('exact_match_normalized');
    expect($findings[0]['headers'])->toBe(['Email', 'email']);
});

// ─── Regression: the original WCG contacts export scenario ──────────────

it('does not produce any findings for a mixed set of unrelated CRM headers', function () {
    $headers  = ['Email', 'Address', 'Address 2', 'Email address', 'Member bundle ID or email'];
    $findings = DuplicateHeaderDetector::detect($headers);

    // Address / Address 2 are carved out. No prefix-subset rule runs now, so
    // Email / Email address are not grouped at this stage either. All other
    // pairs are distinct.
    expect($findings)->toBe([]);
});

it('produces finding groups with the documented shape', function () {
    $findings = DuplicateHeaderDetector::detect(['Email', 'email']);

    expect($findings[0])->toHaveKeys(['rule', 'headers', 'indices', 'summary']);
    expect($findings[0]['summary'])->toBeString();
    expect($findings[0]['indices'])->toEqual([0, 1]);
});

// ─── Wizard trait — review-step state flow ───────────────────────────────

it('seeds review decisions with keep for the first column and ignore for the rest', function () {
    $harness = makeWizardHarness();
    $harness->parsedHeaders     = ['Email', 'email', 'Phone'];
    $harness->duplicateFindings = [
        ['rule' => 'exact_match_normalized', 'headers' => ['Email', 'email'], 'indices' => [0, 1], 'summary' => ''],
    ];
    $harness->data = [];

    $harness->callSeed();

    expect($harness->data['review_decisions']['col_0'])->toBe('keep');
    expect($harness->data['review_decisions']['col_1'])->toBe('ignore');
    expect($harness->data['review_decisions'])->not->toHaveKey('col_2');
});

it('finalize writes ignored_columns and nullifies matching column_map entries', function () {
    $harness = makeWizardHarness();
    $harness->parsedHeaders = ['Email', 'email_address'];
    $harness->data = [
        'review_decisions' => ['col_0' => 'keep', 'col_1' => 'ignore'],
        'column_map'       => ['col_0' => 'email', 'col_1' => 'email'],
        'cf_label_1'       => 'Email Address',
        'cf_handle_1'      => 'email_address',
        'cf_type_1'        => 'text',
    ];

    $harness->callFinalize();

    expect($harness->data['ignored_columns'])->toBe([1]);
    expect($harness->data['column_map']['col_0'])->toBe('email');
    expect($harness->data['column_map']['col_1'])->toBeNull();
    expect($harness->data)->not->toHaveKey('cf_label_1');
    expect($harness->data)->not->toHaveKey('cf_handle_1');
    expect($harness->data)->not->toHaveKey('cf_type_1');
});

it('finalize produces an empty ignored_columns array when all columns are kept', function () {
    $harness = makeWizardHarness();
    $harness->parsedHeaders = ['Email'];
    $harness->data = [
        'review_decisions' => ['col_0' => 'keep'],
        'column_map'       => ['col_0' => 'email'],
    ];

    $harness->callFinalize();

    expect($harness->data['ignored_columns'])->toBe([]);
    expect($harness->data['column_map']['col_0'])->toBe('email');
});

it('sampleValuesFor returns up to five non-blank truncated values', function () {
    $harness = makeWizardHarness();
    $longValue = str_repeat('x', 200);
    $harness->sampleRows = [
        [$longValue, 'ignored'],
        ['', 'ignored'],
        ['short', 'ignored'],
        [null, 'ignored'],
        ['another', 'ignored'],
        ['fourth', 'ignored'],
        ['fifth', 'ignored'],
        ['sixth', 'ignored'],
    ];

    $values = $harness->callSample(0);

    expect($values)->toHaveCount(5);
    expect($values[0])->toEndWith('…');
    expect(mb_strlen($values[0]))->toBe(81);
    expect($values[1])->toBe('short');
    expect($values)->not->toContain('sixth');
});

// ─── Mapping step — ignored columns suppress guesses ────────────────────

it('ignored_columns suppress column_map entries when the mapping step runs', function () {
    // Exercise: what the mapping step expects — after finalizeReviewDecisions
    // runs, any indices in ignored_columns result in column_map[col_N] = null,
    // overriding whatever guessDestination or the saved source would set.
    $harness = makeWizardHarness();
    $harness->parsedHeaders = ['Email', 'email_address', 'First Name'];
    $harness->data = [
        'review_decisions' => ['col_0' => 'keep', 'col_1' => 'ignore'],
        'column_map'       => [
            'col_0' => 'email',
            'col_1' => 'email',
            'col_2' => 'first_name',
        ],
    ];

    $harness->callFinalize();

    expect($harness->data['column_map'])->toBe([
        'col_0' => 'email',
        'col_1' => null,
        'col_2' => 'first_name',
    ]);
});
