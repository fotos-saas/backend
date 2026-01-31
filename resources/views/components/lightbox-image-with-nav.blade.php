@if($imageUrl)
    <div 
        x-data="lightboxNavigator({{ $recordId }}, {{ $prevPhotoId ?? 'null' }}, {{ $nextPhotoId ?? 'null' }}, {{ $currentIndex }}, {{ $totalCount }}, '{{ $imageUrl }}', {{ \Illuminate\Support\Js::from($photosData ?? []) }})"
        x-init="init()"
        @keydown.window.left="hasPrev && navigatePrev()"
        @keydown.window.right="hasNext && navigateNext()"
        style="position: relative; width: 100%; height: 80vh; display: flex; align-items: center; justify-center;"
    >
        <!-- Loading Spinner -->
        <div 
            x-show="isLoading" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10;"
        >
            <svg style="width: 48px; height: 48px; color: #3b82f6;" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity="0.25"/>
                <path d="M12 2 A10 10 0 0 1 22 12" stroke="currentColor" stroke-width="4" stroke-linecap="round">
                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                </path>
            </svg>
        </div>

        <!-- Image - centered with fade transition -->
        <img 
            :src="currentImage" 
            :alt="'Kép előnézet - ID: ' + currentId" 
            @@load="isLoading = false"
            @@error="isLoading = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            style="max-width: calc(100% - 160px); max-height: calc(80vh - 100px); object-fit: contain; display: block; margin: 0 auto; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);"
        />

        <!-- Navigation buttons - positioned over the image -->
        <button 
            x-show="hasPrev"
            @click="navigatePrev()"
            type="button"
            style="position: absolute !important; left: 16px !important; top: 50% !important; transform: translateY(-50%) !important; background-color: rgba(255, 255, 255, 0.95) !important; border-radius: 9999px !important; padding: 16px !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important; border: 2px solid rgba(0, 0, 0, 0.2) !important; z-index: 99999 !important; cursor: pointer !important; transition: all 0.2s !important;"
            @mouseenter="$el.style.transform='translateY(-50%) scale(1.1)'"
            @mouseleave="$el.style.transform='translateY(-50%) scale(1)'"
            aria-label="Előző kép"
            title="Előző kép (balra nyíl)"
        >
            <svg style="width: 32px !important; height: 32px !important; color: #111827 !important;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <button 
            x-show="hasNext"
            @click="navigateNext()"
            type="button"
            style="position: absolute !important; right: 16px !important; top: 50% !important; transform: translateY(-50%) !important; background-color: rgba(255, 255, 255, 0.95) !important; border-radius: 9999px !important; padding: 16px !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important; border: 2px solid rgba(0, 0, 0, 0.2) !important; z-index: 99999 !important; cursor: pointer !important; transition: all 0.2s !important;"
            @mouseenter="$el.style.transform='translateY(-50%) scale(1.1)'"
            @mouseleave="$el.style.transform='translateY(-50%) scale(1)'"
            aria-label="Következő kép"
            title="Következő kép (jobbra nyíl)"
        >
            <svg style="width: 32px !important; height: 32px !important; color: #111827 !important;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Image counter at bottom -->
        <div style="position: absolute !important; bottom: 16px !important; left: 50% !important; transform: translateX(-50%) !important; background-color: rgba(255, 255, 255, 0.95) !important; color: #111827 !important; padding: 8px 16px !important; border-radius: 9999px !important; font-size: 0.875rem !important; font-weight: 600 !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important; border: 1px solid rgba(0, 0, 0, 0.1) !important; z-index: 99999 !important;">
            <span x-text="currentIndex"></span> / <span x-text="totalCount"></span>
        </div>

        <!-- Navigate to photo button - positioned at top right -->
        <button 
            @click="navigateToPhotoInList()"
            type="button"
            style="position: absolute !important; top: 16px !important; right: 16px !important; background-color: rgba(59, 130, 246, 0.95) !important; color: white !important; border-radius: 8px !important; padding: 12px 16px !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important; border: 2px solid rgba(59, 130, 246, 0.8) !important; z-index: 99999 !important; cursor: pointer !important; transition: all 0.2s !important; font-size: 0.875rem !important; font-weight: 600 !important; display: flex !important; align-items: center !important; gap: 8px !important;"
            @mouseenter="$el.style.backgroundColor='rgba(37, 99, 235, 0.95)'"
            @mouseleave="$el.style.backgroundColor='rgba(59, 130, 246, 0.95)'"
            aria-label="Képhez navigálás"
            title="Képhez navigálás - kilép a modalból és csak ezt a képet jeleníti meg"
        >
            <svg style="width: 16px !important; height: 16px !important;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            Képhez navigálás
        </button>
    </div>

@else
    <div class="flex justify-center items-center p-8">
        <p class="text-center text-gray-500 dark:text-gray-400">
            Kép nem található
        </p>
    </div>
@endif

