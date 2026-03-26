<x-filament-panels::page>
    @php $timeline = $this->getTimeline(); @endphp

    @if ($timeline->isEmpty())
        <div class="text-center py-12 text-sm text-gray-500 dark:text-gray-400">
            No timeline entries yet.
        </div>
    @else
        <div class="space-y-2">
            @foreach ($timeline as $item)
                @if ($item->_type === 'note')
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ $item->body }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    by {{ $item->author_name }} · {{ $item->occurred_at->format('M j, Y g:i a') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <button
                                    type="button"
                                    wire:click="mountAction('editNote', @js(['note' => $item->id]))"
                                    class="text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                >Edit</button>
                                <button
                                    type="button"
                                    wire:click="mountAction('deleteNote', @js(['note' => $item->id]))"
                                    class="text-xs text-danger-600 hover:text-danger-500 dark:text-danger-400"
                                >Delete</button>
                            </div>
                        </div>
                    </div>
                @else
                    @php
                        $stripeSessionId = $item->meta['stripe_session_id'] ?? null;
                        $stripeUrl = $stripeSessionId
                            ? 'https://dashboard.stripe.com/checkout/sessions/' . $stripeSessionId
                            : null;
                    @endphp
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-medium capitalize">{{ $item->event }}</span>
                                    {{ $item->actor_label }}@if ($item->description) — {{ $item->description }}@endif
                                </p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $item->created_at->format('M j, Y g:i a') }}
                                </p>
                            </div>
                            @if ($stripeUrl)
                                <a href="{{ $stripeUrl }}"
                                   target="_blank"
                                   rel="nofollow noopener noreferrer"
                                   class="shrink-0 text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                    View in Stripe
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
