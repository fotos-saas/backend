@if($imageUrl)
    <div class="flex justify-center items-center" style="height: 80vh;">
        <img 
            src="{{ $imageUrl }}" 
            alt="Kép előnézet - ID: {{ $recordId }}" 
            class="max-w-full max-h-full object-contain rounded-lg shadow-lg"
            style="display: block; margin: 0 auto;"
        />
    </div>
@else
    <div class="flex justify-center items-center p-8">
        <p class="text-center text-gray-500 dark:text-gray-400">
            Kép nem található
        </p>
    </div>
@endif

