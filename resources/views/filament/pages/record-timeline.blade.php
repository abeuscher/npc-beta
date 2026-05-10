<x-filament-panels::page>
    @php
        $timeline = $this->getTimeline();

        $typeIcon = [
            'call'    => 'heroicon-o-phone',
            'meeting' => 'heroicon-o-users',
            'email'   => 'heroicon-o-envelope',
            'note'    => 'heroicon-o-chat-bubble-left',
            'task'    => 'heroicon-o-clipboard-document-check',
            'letter'  => 'heroicon-o-envelope-open',
            'sms'     => 'heroicon-o-chat-bubble-oval-left',
        ];

        $typeLabel = [
            'call'    => 'Call',
            'meeting' => 'Meeting',
            'email'   => 'Email',
            'note'    => 'Note',
            'task'    => 'Task',
            'letter'  => 'Letter',
            'sms'     => 'SMS',
        ];

        $relativeTime = function ($dt) {
            if (! $dt) return '';
            $c = \Illuminate\Support\Carbon::parse($dt);
            $now = \Illuminate\Support\Carbon::now();
            if ($c->isToday()) {
                return $c->format('g:ia') . ' today';
            }
            if ($c->isYesterday()) {
                return 'Yesterday ' . $c->format('g:ia');
            }
            if ($c->greaterThanOrEqualTo($now->copy()->startOfWeek()) && $c->lessThanOrEqualTo($now)) {
                return $c->format('l');
            }
            if ($c->year === $now->year) {
                return $c->format('M j');
            }
            return $c->format('M j, Y');
        };
    @endphp

    @if ($timeline->isEmpty())
        <div class="np-timeline-empty">
            No timeline entries yet.
        </div>
    @else
        <div class="np-timeline">
            @foreach ($timeline as $item)
                @if ($item->_type === 'note')
                    @php
                        $title = $item->subject
                            ?: \Illuminate\Support\Str::limit(trim(strip_tags($item->body)), 60);
                        $typeKey = $item->type;
                        $icon = $typeIcon[$typeKey] ?? 'heroicon-o-tag';
                        $label = $typeLabel[$typeKey] ?? $typeKey;
                        $badgeSuffix = in_array($typeKey, ['call', 'meeting'], true) && $item->duration_minutes
                            ? ' · ' . $item->duration_minutes . ' min'
                            : '';
                        $status = $item->status ?? 'completed';
                        $followUp = $item->follow_up_at;
                        $followUpState = null;
                        if ($followUp) {
                            $isPast = \Illuminate\Support\Carbon::parse($followUp)->isPast();
                            if ($isPast && $status !== 'completed') {
                                $followUpState = 'overdue';
                            } elseif (! $isPast && $status !== 'completed') {
                                $followUpState = 'pending';
                            }
                        }
                        $metaKeys = is_array($item->meta) ? array_keys($item->meta) : [];
                    @endphp
                    @if ($this->viewMode === 'collapsed')
                        <div class="np-timeline-row">
                            <span class="np-timeline-badge np-timeline-row__type" title="{{ $label }}">
                                <x-dynamic-component :component="$icon" class="np-timeline-badge__icon" />
                                <span>{{ $label }}</span>
                            </span>
                            <span class="np-timeline-row__title">{{ $title }}</span>
                            <span class="np-timeline-row__meta">
                                @if ($status !== 'completed')
                                    <span class="np-timeline-badge np-timeline-badge--muted">{{ \Illuminate\Support\Str::of($status)->replace('_', ' ')->title() }}</span>
                                @endif
                                <span class="np-timeline-timestamp">{{ $relativeTime($item->occurred_at) }}</span>
                                @if ($followUpState === 'pending')
                                    <span class="np-timeline-pill np-timeline-pill--pending">Follow up: {{ $relativeTime($followUp) }}</span>
                                @elseif ($followUpState === 'overdue')
                                    <span class="np-timeline-pill np-timeline-pill--overdue">Overdue: {{ $relativeTime($followUp) }}</span>
                                @endif
                            </span>
                        </div>
                    @else
                    <div class="np-timeline-card">
                        <div class="np-timeline-card__header">
                            <div class="np-timeline-card__title">{{ $title }}</div>
                            <div class="np-timeline-card__header-right">
                                <span class="np-timeline-badge" title="{{ $label }}">
                                    <x-dynamic-component :component="$icon" class="np-timeline-badge__icon" />
                                    <span>{{ $label }}{{ $badgeSuffix }}</span>
                                </span>
                                @if ($status !== 'completed')
                                    <span class="np-timeline-badge np-timeline-badge--muted">{{ \Illuminate\Support\Str::of($status)->replace('_', ' ')->title() }}</span>
                                @endif
                                <span class="np-timeline-timestamp">{{ $relativeTime($item->occurred_at) }}</span>
                                <div class="np-timeline-menu">
                                    @if ($item->can_edit ?? false)
                                        <button
                                            type="button"
                                            wire:click="mountAction('editNote', @js(['note' => $item->id]))"
                                            class="np-timeline-menu__btn"
                                            title="Edit"
                                        >Edit</button>
                                    @endif
                                    @if ($item->can_delete ?? false)
                                        <button
                                            type="button"
                                            wire:click="mountAction('deleteNote', @js(['note' => $item->id]))"
                                            class="np-timeline-menu__btn np-timeline-menu__btn--danger"
                                            title="Delete"
                                        >Delete</button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="np-timeline-card__body">{!! $item->body !!}</div>

                        @if ($item->outcome)
                            <div class="np-timeline-card__outcome">{{ $item->outcome }}</div>
                        @endif

                        @if ($followUpState === 'pending')
                            <div class="np-timeline-pill np-timeline-pill--pending">
                                Follow up: {{ $relativeTime($followUp) }}
                            </div>
                        @elseif ($followUpState === 'overdue')
                            <div class="np-timeline-pill np-timeline-pill--overdue">
                                Overdue: {{ $relativeTime($followUp) }}
                            </div>
                        @endif

                        <div class="np-timeline-card__footer">
                            <span class="np-timeline-author">by {{ $item->author_name }}</span>
                            @if ($item->import_source_name)
                                <a href="{{ $item->import_source_url }}" class="np-timeline-import">
                                    <x-heroicon-o-arrow-up-tray class="np-timeline-import__icon" />
                                    {{ $item->import_source_name }}
                                </a>
                            @endif
                        </div>

                        @if (! empty($metaKeys))
                            <details class="np-timeline-meta">
                                <summary>Source fields</summary>
                                <dl class="np-timeline-meta__list">
                                    @foreach ($item->meta as $metaKey => $metaValue)
                                        <dt>{{ $metaKey }}</dt>
                                        <dd>{{ is_scalar($metaValue) ? $metaValue : json_encode($metaValue) }}</dd>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </div>
                    @endif
                @else
                    <div class="np-timeline-log">
                        <x-heroicon-m-cog-6-tooth class="np-timeline-log__icon" />
                        <span class="np-timeline-log__text">
                            <span class="np-timeline-log__event">{{ \Illuminate\Support\Str::of($item->event)->title() }}</span>
                            {{ $item->actor_label }}@if ($item->description) — {{ $item->description }}@endif
                        </span>
                        <span class="np-timeline-log__timestamp">{{ $relativeTime($item->created_at) }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />

    @vite('resources/scss/admin/record-timeline.scss')
</x-filament-panels::page>
