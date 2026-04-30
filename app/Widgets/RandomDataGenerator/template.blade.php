@if (auth()->user()?->isSuperAdmin())
    @php
        $scrubCounts = app(\App\Services\RandomDataGenerator::class)->scrubCounts();
        $totalScrub  = array_sum($scrubCounts);
    @endphp
    <div class="np-random-data-generator">
        <h3 class="np-random-data-generator__heading">Random Data Generator</h3>
        <p class="np-random-data-generator__description">Super-admin tool. Generates synthetic CRM data tagged <code>source = scrub_data</code> for rehearsals, scale tests, and recovery drills.</p>

        @if (session('rdg_status'))
            <div class="np-random-data-generator__alert np-random-data-generator__alert--success">{{ session('rdg_status') }}</div>
        @endif

        @if ($errors->any())
            <div class="np-random-data-generator__alert np-random-data-generator__alert--error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('filament.admin.dev-tools.random-data.store') }}"
              x-data="{
                  confirming: false,
                  counts: {
                      contacts: {{ (int) old('counts.contacts', 0) }},
                      events: {{ (int) old('counts.events', 0) }},
                      registrations: {{ (int) old('counts.registrations', 0) }},
                      donations: {{ (int) old('counts.donations', 0) }},
                      memberships: {{ (int) old('counts.memberships', 0) }},
                      posts: {{ (int) old('counts.posts', 0) }},
                      products: {{ (int) old('counts.products', 0) }},
                  },
                  total() {
                      return Object.values(this.counts).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
                  }
              }"
              class="np-random-data-generator__form">
            @csrf

            <fieldset class="np-random-data-generator__fields">
                @foreach (['contacts', 'events', 'registrations', 'donations', 'memberships', 'posts', 'products'] as $type)
                    <label class="np-random-data-generator__field">
                        <span class="np-random-data-generator__field-label">{{ ucfirst($type) }}</span>
                        <input type="number"
                               name="counts[{{ $type }}]"
                               min="0"
                               max="1000"
                               x-model.number="counts.{{ $type }}"
                               class="np-random-data-generator__input">
                    </label>
                @endforeach
            </fieldset>

            <p class="np-random-data-generator__hint">Maximum 1000 per record type per generate action.</p>

            <button type="button"
                    @click="confirming = true"
                    x-bind:disabled="total() === 0"
                    class="np-random-data-generator__button np-random-data-generator__button--primary">
                Generate…
            </button>

            <div x-show="confirming" x-cloak class="np-random-data-generator__modal">
                <h4 class="np-random-data-generator__modal-heading">Confirm generation</h4>
                <p class="np-random-data-generator__modal-body">
                    Generate
                    <span x-text="counts.contacts"></span> contacts,
                    <span x-text="counts.events"></span> events,
                    <span x-text="counts.registrations"></span> registrations,
                    <span x-text="counts.donations"></span> donations,
                    <span x-text="counts.memberships"></span> memberships,
                    <span x-text="counts.posts"></span> blog posts,
                    <span x-text="counts.products"></span> products?
                </p>
                <p class="np-random-data-generator__modal-note">
                    Active donations and active paid memberships will also produce matching transaction rows.
                    All generated rows are tagged <code>source = scrub_data</code>.
                </p>
                <div class="np-random-data-generator__modal-actions">
                    <button type="button" @click="confirming = false" class="np-random-data-generator__button">Cancel</button>
                    <button type="submit" class="np-random-data-generator__button np-random-data-generator__button--primary">Confirm</button>
                </div>
            </div>
        </form>

        <hr class="np-random-data-generator__divider">

        <h4 class="np-random-data-generator__subheading">Wipe scrub data</h4>
        @if ($totalScrub === 0)
            <p class="np-random-data-generator__hint">No scrub-tagged rows currently exist.</p>
        @else
            <p class="np-random-data-generator__counts">
                Currently tagged <code>scrub_data</code>:
                {{ $scrubCounts['contacts'] }} contacts,
                {{ $scrubCounts['events'] }} events,
                {{ $scrubCounts['registrations'] }} registrations,
                {{ $scrubCounts['donations'] }} donations,
                {{ $scrubCounts['memberships'] }} memberships,
                {{ $scrubCounts['posts'] }} blog posts,
                {{ $scrubCounts['products'] }} products,
                {{ $scrubCounts['transactions'] }} transactions.
            </p>
            <form method="POST"
                  action="{{ route('filament.admin.dev-tools.random-data.wipe') }}"
                  x-data="{ confirming: false }"
                  class="np-random-data-generator__form">
                @csrf

                <button type="button"
                        @click="confirming = true"
                        class="np-random-data-generator__button np-random-data-generator__button--danger">
                    Wipe scrub data…
                </button>

                <div x-show="confirming" x-cloak class="np-random-data-generator__modal">
                    <h4 class="np-random-data-generator__modal-heading">Confirm wipe</h4>
                    <p class="np-random-data-generator__modal-body">
                        Permanently delete {{ $scrubCounts['contacts'] }} contacts,
                        {{ $scrubCounts['events'] }} events,
                        {{ $scrubCounts['registrations'] }} registrations,
                        {{ $scrubCounts['donations'] }} donations,
                        {{ $scrubCounts['memberships'] }} memberships,
                        {{ $scrubCounts['posts'] }} blog posts,
                        {{ $scrubCounts['products'] }} products,
                        and {{ $scrubCounts['transactions'] }} transactions tagged <code>scrub_data</code>?
                    </p>
                    <p class="np-random-data-generator__modal-note">
                        Only rows where <code>source = scrub_data</code> are affected; real data is untouched.
                    </p>
                    <div class="np-random-data-generator__modal-actions">
                        <button type="button" @click="confirming = false" class="np-random-data-generator__button">Cancel</button>
                        <button type="submit" class="np-random-data-generator__button np-random-data-generator__button--danger">Confirm wipe</button>
                    </div>
                </div>
            </form>
        @endif

        <hr class="np-random-data-generator__divider">

        <h4 class="np-random-data-generator__subheading">Seed widget demo collections</h4>
        <p class="np-random-data-generator__hint">
            Runs each widget's <code>demoSeeder</code> to populate sample collections (board members, carousel slides, bar-chart data, logo garden, etc.).
            Idempotent — collections seeded with <code>source = demo</code>, distinct from <code>scrub_data</code>; safe to re-run.
        </p>
        <form method="POST"
              action="{{ route('filament.admin.dev-tools.random-data.seed-collections') }}"
              class="np-random-data-generator__form">
            @csrf
            <button type="submit" class="np-random-data-generator__button">Seed widget collections</button>
        </form>
    </div>
@endif
