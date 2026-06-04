@php
    $stateExpr = json_encode($this->sampleState());
    $resources = $this->sampleResources();
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <p class="max-w-2xl text-sm text-gray-500 dark:text-gray-400">
            Create as many roles as you need — Administrator, Editor, Volunteer, Board Member — and control
            exactly what each one can see and do, down to read, write, and delete on every part of the system.
            <span class="italic">This is a live sample: toggle anything you like — nothing here is saved.</span>
        </p>

        {{-- The tour anchors its Roles step here; on the real RoleResource list
             the same `resource.records` marker sits before the table. --}}
        <div data-tour="resource.records" class="np-tour-anchor"></div>
        <x-admin.permission-matrix :state-expr="$stateExpr" :resources="$resources" />
    </div>
</x-filament-panels::page>
