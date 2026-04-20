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
                                    <button
                                        type="button"
                                        wire:click="mountAction('editNote', @js(['note' => $item->id]))"
                                        class="np-timeline-menu__btn"
                                        title="Edit"
                                    >Edit</button>
                                    <button
                                        type="button"
                                        wire:click="mountAction('deleteNote', @js(['note' => $item->id]))"
                                        class="np-timeline-menu__btn np-timeline-menu__btn--danger"
                                        title="Delete"
                                    >Delete</button>
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

    @push('styles')
    <style>
        .np-timeline-empty {
            padding: 3rem 0;
            text-align: center;
            font-size: 0.875rem;
            color: var(--np-control-chip-text);
        }
        .np-timeline {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .np-timeline-card {
            background: var(--np-control-chip-bg);
            border: 1px solid var(--np-control-border);
            border-radius: var(--np-control-radius);
            padding: 1rem;
        }
        .np-timeline-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }
        .np-timeline-card__title {
            flex: 1 1 auto;
            min-width: 0;
            font-weight: 600;
            color: var(--np-control-chip-text-active);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .np-timeline-card__header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        .np-timeline-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.125rem 0.5rem;
            border: 1px solid var(--np-control-chip-border);
            border-radius: 9999px;
            background: var(--np-control-chip-bg);
            color: var(--np-control-chip-text);
            font-size: 0.75rem;
            line-height: 1.25;
        }
        .np-timeline-badge__icon {
            width: 0.875rem;
            height: 0.875rem;
            color: var(--np-control-icon-default);
        }
        .np-timeline-badge--muted {
            background: var(--np-control-hover-tint);
            border-color: var(--np-control-border);
        }
        .np-timeline-timestamp {
            font-size: 0.75rem;
            color: var(--np-control-chip-text);
            white-space: nowrap;
        }
        .np-timeline-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.25rem;
        }
        .np-timeline-menu__btn {
            font-size: 0.75rem;
            color: var(--np-control-chip-text);
            background: transparent;
            border: none;
            padding: 0.125rem 0.25rem;
            cursor: pointer;
            transition: var(--np-control-transition);
        }
        .np-timeline-menu__btn:hover {
            color: var(--np-control-chip-text-active);
        }
        .np-timeline-menu__btn--danger:hover {
            color: #b91c1c;
        }
        .np-timeline-card__body {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--np-control-chip-text-active);
        }
        .np-timeline-card__body p { margin: 0 0 0.5rem; }
        .np-timeline-card__body p:last-child { margin-bottom: 0; }
        .np-timeline-card__body ul,
        .np-timeline-card__body ol { margin: 0 0 0.5rem 1.25rem; }
        .np-timeline-card__outcome {
            margin-top: 0.5rem;
            font-size: 0.8125rem;
            font-style: italic;
            color: var(--np-control-chip-text);
        }
        .np-timeline-pill {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.125rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            border: 1px solid var(--np-control-chip-border);
            background: var(--np-control-chip-bg);
            color: var(--np-control-chip-text);
        }
        .np-timeline-pill--overdue {
            border-color: #d97706;
            color: #b45309;
            background: #fef3c7;
        }
        [data-theme="dark"] .np-timeline-pill--overdue,
        .dark .np-timeline-pill--overdue {
            border-color: #f59e0b;
            color: #fbbf24;
            background: rgba(245, 158, 11, 0.12);
        }
        .np-timeline-card__footer {
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--np-control-chip-text);
        }
        .np-timeline-author {
            color: var(--np-control-chip-text);
        }
        .np-timeline-import {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            background: var(--np-control-hover-tint);
            color: var(--np-control-chip-text-active);
            text-decoration: none;
        }
        .np-timeline-import__icon {
            width: 0.75rem;
            height: 0.75rem;
        }
        .np-timeline-meta {
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }
        .np-timeline-meta summary {
            cursor: pointer;
            color: var(--np-control-chip-text);
            user-select: none;
        }
        .np-timeline-meta__list {
            margin-top: 0.375rem;
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 0.125rem 0.75rem;
        }
        .np-timeline-meta__list dt {
            font-weight: 600;
            color: var(--np-control-chip-text-active);
        }
        .np-timeline-meta__list dd {
            margin: 0;
            color: var(--np-control-chip-text);
            overflow-wrap: anywhere;
        }
        .np-timeline-log {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
            color: var(--np-control-chip-text);
            border-bottom: 1px solid var(--np-control-border);
        }
        .np-timeline-log__icon {
            width: 0.875rem;
            height: 0.875rem;
            color: var(--np-control-icon-default);
            flex-shrink: 0;
        }
        .np-timeline-log__text {
            flex: 1 1 auto;
        }
        .np-timeline-log__event {
            font-weight: 500;
            color: var(--np-control-chip-text-active);
        }
        .np-timeline-log__timestamp {
            color: var(--np-control-chip-text);
            white-space: nowrap;
        }
    </style>
    @endpush
</x-filament-panels::page>
