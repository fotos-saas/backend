@if(!empty($warnings))
    <div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-4 border border-warning-200 dark:border-warning-800">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" />
            <div>
                <h4 class="font-medium text-warning-800 dark:text-warning-200">Figyelmeztetések</h4>
                <ul class="mt-2 list-disc list-inside space-y-1 text-warning-700 dark:text-warning-300">
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@else
    <p class="text-gray-500">Nincs figyelmeztetés.</p>
@endif
