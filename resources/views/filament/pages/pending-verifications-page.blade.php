<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-warning-100 dark:bg-warning-900">
                    <x-heroicon-o-clock class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Jóváhagyásra vár</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalPending() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-danger-100 dark:bg-danger-900">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ütközéssel</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalWithConflict() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-info-100 dark:bg-info-900">
                    <x-heroicon-o-folder class="w-6 h-6 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Érintett projektek</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getAffectedProjects() }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    @if($this->getTotalPending() > 0)
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-info-500" />
                    <span>Tudnivalók</span>
                </div>
            </x-slot>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p class="text-gray-600 dark:text-gray-400">
                    Ha egy vendég olyan személyt választ a regisztrációnál, akit már más "elfoglalt",
                    akkor a session <strong>pending</strong> státuszba kerül.
                    Itt eldöntheted, hogy:
                </p>
                <ul class="text-gray-600 dark:text-gray-400 list-disc list-inside">
                    <li><strong>Jóváhagyod</strong> - Az új vendég lesz a személy tulajdonosa (a régi elveszíti)</li>
                    <li><strong>Elutasítod</strong> - A vendég visszairányításra kerül, válasszon másik személyt</li>
                </ul>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
