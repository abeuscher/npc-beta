{{-- Slim panel-wide delinquency warning (client billing, CB2). Injected at
     panels::page.start on every admin screen for manage_account holders when a
     payment is overdue / in grace, so the person who can fix it sees it before
     the admin panel locks — not at it. Suppressed on the Account page itself,
     where the prominent banner already renders. --}}
<div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 flex-shrink-0 text-amber-500" />
    <span class="font-medium">
        A billing payment needs attention.
        @if ($locksAt)
            Admin access will be paused on {{ $locksAt }} unless it’s resolved.
        @else
            Admin access may be paused unless it’s resolved.
        @endif
    </span>
    <a href="{{ $accountUrl }}" class="font-semibold underline underline-offset-2">Review account →</a>
</div>
