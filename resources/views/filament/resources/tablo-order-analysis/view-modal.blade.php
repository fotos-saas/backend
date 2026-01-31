<div class="space-y-6">
    {{-- Figyelmeztetések --}}
    @if($analysis->hasWarnings())
        <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4 border border-warning-200 dark:border-warning-700">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500 flex-shrink-0 mt-0.5" />
                <div>
                    <h4 class="font-medium text-warning-800 dark:text-warning-200">Figyelmeztetések</h4>
                    <ul class="mt-2 text-sm text-warning-700 dark:text-warning-300 list-disc list-inside space-y-1">
                        @foreach($analysis->warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Címkék --}}
    @if($analysis->tags)
        <div class="flex flex-wrap gap-2">
            @foreach($analysis->tags as $tag)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200">
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- AI Összefoglaló --}}
    @if($analysis->ai_summary)
        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-700">
            <div class="flex items-start gap-3">
                <x-heroicon-o-sparkles class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                <div>
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">AI Összefoglaló</h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300">{{ $analysis->ai_summary }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Kapcsolattartó --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-user class="w-4 h-4" />
                Kapcsolattartó
            </h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Név:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $analysis->contact_name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Telefon:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        @if($analysis->contact_phone)
                            <a href="tel:{{ $analysis->contact_phone }}" class="text-primary-600 hover:underline">
                                {{ $analysis->contact_phone }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Email:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        @if($analysis->contact_email)
                            <a href="mailto:{{ $analysis->contact_email }}" class="text-primary-600 hover:underline">
                                {{ $analysis->contact_email }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Iskola & Osztály --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-academic-cap class="w-4 h-4" />
                Iskola & Osztály
            </h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Iskola:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium text-right">{{ $analysis->school_name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Osztály:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->class_name ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Diákok:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->student_count ?? 0 }} fő</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Tanárok:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->teacher_count ?? 0 }} fő</dd>
                </div>
            </dl>
        </div>

        {{-- Design preferenciák --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-paint-brush class="w-4 h-4" />
                Design preferenciák
            </h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Méret:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->tablo_size ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Betűtípus:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->font_style ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Szín:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->color_scheme ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Háttér:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->background_style ?? '-' }}</dd>
                </div>
            </dl>
            @if($analysis->special_notes)
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <dt class="text-gray-500 dark:text-gray-400 text-xs uppercase">Megjegyzés:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1">{{ $analysis->special_notes }}</dd>
                </div>
            @endif
        </div>

        {{-- PDF & Email --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-document class="w-4 h-4" />
                Forrás
            </h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">PDF:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $analysis->pdf_filename ?? '-' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Elemezve:</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        {{ $analysis->analyzed_at ? $analysis->analyzed_at->format('Y.m.d H:i') : '-' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Diákok lista --}}
    @if($analysis->student_list && count($analysis->student_list) > 0)
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-users class="w-4 h-4" />
                Diákok ({{ count($analysis->student_list) }} fő)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
                @foreach($analysis->student_list as $index => $student)
                    <div class="flex items-center gap-2 py-1">
                        <span class="text-gray-400 text-xs w-5 text-right">{{ $index + 1 }}.</span>
                        <span class="text-gray-900 dark:text-gray-100">{{ $student['name'] ?? $student }}</span>
                        @if(isset($student['note']) && $student['note'])
                            <span class="text-xs text-gray-500">({{ $student['note'] }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tanárok lista --}}
    @if($analysis->teacher_list && count($analysis->teacher_list) > 0)
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3 flex items-center gap-2">
                <x-heroicon-o-user-group class="w-4 h-4" />
                Tanárok ({{ count($analysis->teacher_list) }} fő)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                @foreach($analysis->teacher_list as $teacher)
                    <div class="flex items-center justify-between py-1 border-b border-gray-200 dark:border-gray-700 last:border-0">
                        <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $teacher['name'] ?? $teacher }}</span>
                        @if(isset($teacher['role']) && $teacher['role'])
                            <span class="text-xs text-gray-500 bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded">{{ $teacher['role'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
