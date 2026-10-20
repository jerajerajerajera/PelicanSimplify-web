<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-sm text-gray-600 dark:text-gray-400">Manage which servers appear on the Deckzwabbers website. New servers inherit the Show on website choice from SSC; directory edits take priority.</p>
        <p class="mt-2 text-sm font-medium">{{ config('server-directory.mode', 'test') === 'test' ? 'Test network · Allocation addresses may be shown.' : 'Production · Explicit join addresses are required.' }}</p>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
