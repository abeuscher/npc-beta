<x-filament-panels::page>
    <div class="dashboard-settings-page" style="display:flex; flex-direction:column; gap:1rem;">
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <label for="dashboard-settings-role" style="font-weight:600;">Role:</label>
            <select
                id="dashboard-settings-role"
                wire:change="switchRole($event.target.value)"
                wire:model.live="roleId"
                style="border:1px solid #d1d5db; border-radius:0.375rem; padding:0.375rem 0.5rem;"
            >
                @foreach ($this->getRoleOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @php($config = $this->getSelectedConfig())

        @if ($config)
            @livewire('dashboard-builder', ['dashboardConfigId' => $config->id], key('dashboard-builder-' . $config->id))
        @else
            <div style="padding:1.5rem; border:2px dashed #e5e7eb; border-radius:0.5rem; text-align:center;">
                <p style="margin-bottom:1rem; color:#4b5563;">
                    No dashboard is configured for <strong>{{ $this->getSelectedRoleLabel() }}</strong> yet.
                </p>
                <button
                    type="button"
                    wire:click="createConfigForSelectedRole"
                    class="fi-btn fi-btn-size-md fi-color-primary"
                    style="padding:0.5rem 1rem; background: var(--c-primary-600, #4f46e5); color: white; border-radius:0.5rem;"
                >
                    Create dashboard for {{ $this->getSelectedRoleLabel() }}
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
