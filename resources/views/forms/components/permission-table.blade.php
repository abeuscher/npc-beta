@php
    $statePath  = $getStatePath();
    $resources  = $getResources();
    $groups     = \App\Forms\Components\PermissionTable::$groups;
    $allPerms   = collect($resources)->flatMap(
        fn ($r) => collect($groups)->flatMap(fn ($actions) => collect($actions)->map(fn ($a) => "{$a}_{$r}"))
    )->values()->all();
@endphp

<div
    x-data="{
        state: $wire.entangle('{{ $statePath }}'),

        perms: @js($groups),
        allPerms: @js($allPerms),
        resources: @js($resources),

        hasGroup(resource, group) {
            const current = this.state || [];
            return this.perms[group].every(action => current.includes(action + '_' + resource));
        },

        toggleGroup(resource, group) {
            const actions = this.perms[group];
            const current = [...(this.state || [])];
            const allChecked = actions.every(a => current.includes(a + '_' + resource));
            if (allChecked) {
                const drop = actions.map(a => a + '_' + resource);
                this.state = current.filter(p => !drop.includes(p));
            } else {
                const add = actions.map(a => a + '_' + resource).filter(p => !current.includes(p));
                this.state = [...current, ...add];
            }
        },

        columnAllChecked(group) {
            return this.resources.every(r => this.hasGroup(r, group));
        },

        toggleColumn(group) {
            if (this.columnAllChecked(group)) {
                this.resources.forEach(r => {
                    const drop = this.perms[group].map(a => a + '_' + r);
                    this.state = (this.state || []).filter(p => !drop.includes(p));
                });
            } else {
                this.resources.forEach(r => {
                    const add = this.perms[group].map(a => a + '_' + r).filter(p => !(this.state || []).includes(p));
                    this.state = [...(this.state || []), ...add];
                });
            }
        },

        selectAll() {
            const existing = (this.state || []).filter(p => !this.allPerms.includes(p));
            this.state = [...existing, ...this.allPerms];
        },

        clearAll() {
            this.state = (this.state || []).filter(p => !this.allPerms.includes(p));
        }
    }"
>
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Resource</th>
                    @foreach (['read', 'write', 'delete'] as $group)
                        <th class="px-4 py-3 text-center w-24">
                            <button
                                type="button"
                                @click="toggleColumn('{{ $group }}')"
                                class="font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400
                                       dark:hover:text-primary-400 transition-colors"
                                :class="{ 'text-primary-600 dark:text-primary-400': columnAllChecked('{{ $group }}') }"
                                title="Toggle all {{ ucfirst($group) }}"
                            >
                                {{ ucfirst($group) }}
                            </button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                @foreach ($resources as $resource)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-4 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                            {{ str($resource)->replace('_', ' ')->title() }}
                        </td>
                        @foreach (['read', 'write', 'delete'] as $group)
                            <td class="px-4 py-2.5 text-center">
                                <input
                                    type="checkbox"
                                    :checked="hasGroup('{{ $resource }}', '{{ $group }}')"
                                    @change="toggleGroup('{{ $resource }}', '{{ $group }}')"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm
                                           focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700
                                           dark:checked:bg-primary-500"
                                >
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3 flex justify-end gap-2">
        <button type="button" @click="clearAll()"
            class="text-sm font-semibold text-danger-600 hover:text-danger-500 transition-colors duration-75 dark:text-danger-400 dark:hover:text-danger-300">
            Clear
        </button>
        <button type="button" @click="selectAll()"
            class="fi-btn fi-btn-color-gray fi-size-sm fi-btn-size-sm inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold shadow-sm outline-none transition duration-75 bg-white text-gray-950 hover:bg-gray-50 border border-gray-300 dark:bg-white/5 dark:text-white dark:border-white/10 dark:hover:bg-white/10">
            Select all
        </button>
    </div>
</div>
