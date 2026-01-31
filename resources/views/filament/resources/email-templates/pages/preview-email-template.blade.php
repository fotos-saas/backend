<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Email Subject Preview --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Email Tárgy</h3>
            <div class="text-gray-700 dark:text-gray-300 font-medium">
                {{ $this->getResolvedSubject() }}
            </div>
        </div>

        {{-- Email Body Preview --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Email Tartalom Előnézet</h3>
            <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                <iframe 
                    srcdoc="{{ e($this->getPreviewHtml()) }}"
                    class="w-full border-0 block"
                    style="width: 100%; min-height: 600px; display: block;"
                    sandbox="allow-same-origin"
                ></iframe>
            </div>
        </div>

        {{-- Test Data Info --}}
        <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4 text-blue-900 dark:text-blue-100">
                <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Teszt Adatok
            </h3>
            <p class="text-sm text-blue-800 dark:text-blue-200 mb-3">
                Az előnézet az alábbi teszt adatokkal jeleníti meg az email tartalmát:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="font-semibold text-blue-900 dark:text-blue-100">Felhasználó:</span>
                    <span class="text-blue-800 dark:text-blue-200">Teszt Felhasználó (teszt@example.com)</span>
                </div>
                <div>
                    <span class="font-semibold text-blue-900 dark:text-blue-100">Album:</span>
                    <span class="text-blue-800 dark:text-blue-200">Őszi Tablófotózás 2025 (42 kép)</span>
                </div>
                <div>
                    <span class="font-semibold text-blue-900 dark:text-blue-100">Megrendelés:</span>
                    <span class="text-blue-800 dark:text-blue-200">#12345 (25 000 Ft)</span>
                </div>
                <div>
                    <span class="font-semibold text-blue-900 dark:text-blue-100">Dátum:</span>
                    <span class="text-blue-800 dark:text-blue-200">{{ now()->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

