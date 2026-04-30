@if (auth()->user()?->isSuperAdmin())
    @php
        $service       = app(\App\Services\Setup\SetupChecklist::class);
        $items         = $service->items();
        $isFirstRun    = $service->isFirstRun();
        $statusMessage = session('setup_checklist_status');

        $sectionLabels = [
            \App\Services\Setup\SetupChecklist::CATEGORY_REQUIRED_TO_BOOT     => 'Required to launch',
            \App\Services\Setup\SetupChecklist::CATEGORY_REQUIRED_FOR_FEATURE => 'Required for specific features',
            \App\Services\Setup\SetupChecklist::CATEGORY_OPTIONAL             => 'Optional',
        ];

        $grouped = collect($items);
        if (! $isFirstRun) {
            $grouped = $grouped->reject(fn ($item) => $item['status'] === \App\Services\Setup\SetupChecklist::STATUS_DONE);
        }
        $grouped = $grouped->groupBy('category');
    @endphp

    <div class="np-setup-checklist np-setup-checklist--{{ $isFirstRun ? 'first-run' : 'health-check' }}">
        <h3 class="np-setup-checklist__heading">Setup Checklist</h3>
        <p class="np-setup-checklist__description">
            @if ($isFirstRun)
                Walk through these items to bring the install to a usable state. Each row links to the page where it's configured.
            @else
                Health check — only items needing attention are shown.
            @endif
        </p>

        @if ($statusMessage)
            <div class="np-setup-checklist__alert np-setup-checklist__alert--success">{{ $statusMessage }}</div>
        @endif

        @if ($grouped->isEmpty())
            <p class="np-setup-checklist__empty">All items are configured.</p>
        @else
            @foreach ($sectionLabels as $category => $label)
                @php $sectionItems = $grouped->get($category, collect()); @endphp
                @if ($sectionItems->isNotEmpty())
                    <section class="np-setup-checklist__section">
                        <h4 class="np-setup-checklist__section-heading">{{ $label }}</h4>
                        <ul class="np-setup-checklist__list">
                            @foreach ($sectionItems as $item)
                                <li class="np-setup-checklist__item np-setup-checklist__item--{{ str_replace('_', '-', $item['status']) }}">
                                    <span class="np-setup-checklist__pill np-setup-checklist__pill--{{ str_replace('_', '-', $item['status']) }}">
                                        @switch($item['status'])
                                            @case(\App\Services\Setup\SetupChecklist::STATUS_DONE)         Done @break
                                            @case(\App\Services\Setup\SetupChecklist::STATUS_INCOMPLETE)   Incomplete @break
                                            @case(\App\Services\Setup\SetupChecklist::STATUS_WARNING)      Warning @break
                                            @default                                                       Optional
                                        @endswitch
                                    </span>
                                    <div class="np-setup-checklist__body">
                                        <div class="np-setup-checklist__title">{{ $item['title'] }}</div>
                                        <div class="np-setup-checklist__copy">{{ $item['description'] }}</div>
                                        @if ($item['message'])
                                            <div class="np-setup-checklist__message">{{ $item['message'] }}</div>
                                        @endif
                                    </div>
                                    <a href="{{ $item['configure_url'] }}" class="np-setup-checklist__link">Configure →</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        @endif

        <div class="np-setup-checklist__actions">
            @if ($isFirstRun)
                <form method="POST" action="{{ route('filament.admin.setup-checklist.mark-complete') }}">
                    @csrf
                    <button type="submit" class="np-setup-checklist__button np-setup-checklist__button--primary">
                        Mark setup complete
                    </button>
                </form>
            @else
                <form method="POST"
                      action="{{ route('filament.admin.setup-checklist.reset') }}"
                      x-data="{ confirming: false }"
                      class="np-setup-checklist__form">
                    @csrf
                    <button type="button"
                            @click="confirming = true"
                            class="np-setup-checklist__button np-setup-checklist__button--danger">
                        Reset install state
                    </button>

                    <div x-show="confirming" x-cloak class="np-setup-checklist__modal">
                        <h4 class="np-setup-checklist__modal-heading">Reset install state?</h4>
                        <p class="np-setup-checklist__modal-body">
                            The widget will return to first-run mode and show every checklist item again.
                            No setting values are changed; only the "setup complete" flag is cleared.
                        </p>
                        <div class="np-setup-checklist__modal-actions">
                            <button type="button" @click="confirming = false" class="np-setup-checklist__button">Cancel</button>
                            <button type="submit" class="np-setup-checklist__button np-setup-checklist__button--danger">Confirm reset</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
