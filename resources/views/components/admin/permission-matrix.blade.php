{{--
    The role permission grid. Single source of truth for the matrix markup,
    shared by the real role form (state entangled to Livewire) and the demo
    tour's Roles showcase (state passed as a plain illustrative array). The
    `permissionTable` Alpine component drives the checkboxes either way, so the
    showcase reuses the real UI rather than mirroring it — no drift.

    Props:
      stateExpr — raw JS expression for the component's state argument
                  ($wire.entangle('…') in the form; a @js array in the showcase)
      resources — list of resource names (rows)
      groups    — optional override of the read/write/delete column mapping
--}}
@props(['stateExpr', 'resources', 'groups' => null])
@php
    $groups = $groups ?? \App\Forms\Components\PermissionTable::$groups;
    $allPerms = collect($resources)->flatMap(
        fn ($r) => collect($groups)->flatMap(fn ($actions) => collect($actions)->map(fn ($a) => "{$a}_{$r}"))
    )->values()->all();
@endphp

<div
    x-data="permissionTable({!! $stateExpr !!}, @js($groups), @js($allPerms), @js($resources))"
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
