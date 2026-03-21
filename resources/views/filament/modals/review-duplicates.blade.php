@php
    use App\Models\Contact;

    $pair     = $pairs[$currentIndex] ?? null;
    $total    = count($pairs);
    $contactA = $pair ? Contact::find($pair['a_id']) : null;
    $contactB = $pair ? Contact::find($pair['b_id']) : null;
@endphp

@if($pair && $contactA && $contactB)
    <div class="space-y-5">

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Reviewing pair {{ $currentIndex + 1 }} of {{ $total }}
        </p>

        <div class="grid grid-cols-2 gap-4">

            {{-- Contact A --}}
            <div @class([
                'rounded-lg border p-4',
                'border-primary-500 ring-2 ring-primary-300 bg-primary-50 dark:bg-primary-950' => $survivorId === $pair['a_id'],
                'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' => $survivorId !== $pair['a_id'],
            ])>
                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                    <input type="radio"
                           wire:model.live="survivorId"
                           value="{{ $pair['a_id'] }}"
                           class="text-primary-600">
                    <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Keep this contact</span>
                </label>
                <dl class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Name</dt>
                        <dd>{{ $contactA->display_name ?: '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Email</dt>
                        <dd>{{ $contactA->email ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Phone</dt>
                        <dd>{{ $contactA->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Added</dt>
                        <dd>{{ $contactA->created_at?->toDateString() ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Contact B --}}
            <div @class([
                'rounded-lg border p-4',
                'border-primary-500 ring-2 ring-primary-300 bg-primary-50 dark:bg-primary-950' => $survivorId === $pair['b_id'],
                'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' => $survivorId !== $pair['b_id'],
            ])>
                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                    <input type="radio"
                           wire:model.live="survivorId"
                           value="{{ $pair['b_id'] }}"
                           class="text-primary-600">
                    <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Keep this contact</span>
                </label>
                <dl class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Name</dt>
                        <dd>{{ $contactB->display_name ?: '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Email</dt>
                        <dd>{{ $contactB->email ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Phone</dt>
                        <dd>{{ $contactB->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-gray-500 w-16 shrink-0">Added</dt>
                        <dd>{{ $contactB->created_at?->toDateString() ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

        </div>

        <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <x-filament::button wire:click="mergePair" color="primary" size="sm">
                Merge
            </x-filament::button>
            <x-filament::button wire:click="dismissPair" color="warning" size="sm">
                Not a Duplicate
            </x-filament::button>
            <x-filament::button wire:click="skipPair" color="gray" size="sm">
                Skip
            </x-filament::button>
        </div>

    </div>
@else
    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
        Could not load contact data for this pair.
    </div>
@endif
