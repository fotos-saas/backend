<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-info-100 dark:bg-info-900">
                    <x-heroicon-o-folder class="w-6 h-6 text-info-600 dark:text-info-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Projektek</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalProjects() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-warning-100 dark:bg-warning-900">
                    <x-heroicon-o-academic-cap class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Hiányzó tanárok</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalMissingTeachers() }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-lg bg-primary-100 dark:bg-primary-900">
                    <x-heroicon-o-user-group class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Hiányzó diákok</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->getTotalMissingStudents() }}</p>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
