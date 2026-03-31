<div class="space-y-4">
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Theme SCSS</label>
        <textarea
            wire:model="themeScss"
            rows="30"
            style="font-family: monospace; font-size: 0.85rem;"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        ></textarea>
    </div>

    <div>
        <button
            type="button"
            wire:click="saveAndBuildScss"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="saveAndBuildScss">Save & Build</span>
            <span wire:loading wire:target="saveAndBuildScss">Building…</span>
        </button>
    </div>

    @if ($buildOutput !== '')
        <div class="space-y-2">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Last build output</h3>
            <pre class="overflow-x-auto rounded-lg border p-4 text-xs font-mono {{ $buildSuccess ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-200' }}">{{ $buildOutput }}</pre>
        </div>
    @endif
</div>
