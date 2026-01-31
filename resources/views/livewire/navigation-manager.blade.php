<div class="flex flex-col gap-6" x-data="{
    search: @entangle('search'),
    showSaved: false,
    savingInProgress: false
}"
@navigation-updated.window="
    // Force page reload to refresh sidebar navigation
    setTimeout(() => {
        window.location.reload();
    }, 500);
">
    {{-- Sticky Header with Actions --}}
    @if($selectedRole)
        <div class="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-700 shadow-sm -mx-6 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ ucfirst(str_replace('_', ' ', $selectedRole->name)) }}
                            </h2>
                            <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <span class="font-semibold text-orange-600 dark:text-orange-400">{{ count(array_filter($navigationItems, fn($item) => $item['is_configured'])) }}</span>
                                <span>/</span>
                                <span>{{ count($navigationItems) }} menüpont konfigurálva</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    {{-- Search Input --}}
                    <div class="relative">
                        <input
                            type="text"
                            x-model="search"
                            placeholder="Keresés..."
                            class="w-64 pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    {{-- Manage Groups Button --}}
                    <button
                        wire:click="openManageGroupsModal"
                        type="button"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-all flex items-center gap-2 shadow-sm"
                        title="Navigációs csoportok kezelése">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        Csoportok
                    </button>

                    {{-- New Group Button --}}
                    <button
                        wire:click="openNewGroupModal"
                        type="button"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition-all flex items-center gap-2 shadow-sm"
                        title="Új navigációs csoport hozzáadása">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Új Csoport
                    </button>

                    {{-- Expand/Collapse All --}}
                    <button
                        wire:click="expandAll"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                            </svg>
                            Mind kinyit
                        </span>
                    </button>

                    <button
                        wire:click="collapseAll"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            Mind becsuk
                        </span>
                    </button>
                </div>
            </div>

            {{-- Progress Bar --}}
            @php
                $configuredCount = count(array_filter($navigationItems, fn($item) => $item['is_configured']));
                $totalCount = count($navigationItems);
                $progressPercent = $totalCount > 0 ? ($configuredCount / $totalCount * 100) : 0;
            @endphp
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                    <span>Konfigurált menüpontok: {{ $configuredCount }} / {{ $totalCount }}</span>
                    <span>{{ number_format($progressPercent, 1) }}%</span>
                </div>
                <div class="h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-orange-500 to-orange-600 rounded-full transition-all duration-500 ease-out"
                        style="width: {{ $progressPercent }}%">
                    </div>
                </div>
            </div>

            {{-- Toast Notification --}}
            <div 
                x-show="showSaved" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed top-4 right-4 bg-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3"
                style="display: none;">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">Módosítások automatikusan mentve!</span>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Role Selection (Left Sidebar) --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden sticky top-32">
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-900 px-5 py-5 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <svg class="w-6 h-6 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        Szerepkörök
                    </h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mt-1">{{ count($roles) }} szerepkör kezelése</p>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach($roles as $role)
                        <button
                            wire:click="selectRole({{ $role->id }})"
                            class="group relative block w-full text-left px-3.5 py-3 rounded-lg transition-all duration-200
                                {{ $selectedRoleId === $role->id
                                    ? 'bg-gradient-to-r from-orange-50 to-orange-100 dark:from-orange-950/50 dark:to-orange-900/50 text-orange-900 dark:text-orange-100 shadow-sm ring-1 ring-orange-200 dark:ring-orange-800'
                                    : 'bg-white dark:bg-gray-700/30 text-gray-700 dark:text-gray-300 hover:bg-slate-50 dark:hover:bg-gray-700/50 hover:shadow-sm border border-transparent hover:border-slate-200 dark:hover:border-slate-700' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    @if($selectedRoleId === $role->id)
                                        <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse shadow-sm"></div>
                                    @else
                                        <div class="w-2 h-2 bg-slate-300 dark:bg-slate-600 rounded-full group-hover:bg-orange-400 transition-colors"></div>
                                    @endif
                                    <span class="font-medium text-sm">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
                                </div>
                                @if($selectedRoleId === $role->id)
                                    <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Navigation Items (Main Area) --}}
        <div class="lg:col-span-3">
            @if($selectedRole)
                {{-- Group navigation items by their group --}}
                @php
                    $groupedItems = collect($filteredItems)->groupBy(function($item) {
                        return $item['group'] ?? 'ungrouped';
                    });
                    $groupedItems = $groupedItems->sortBy(function($items, $groupKey) use ($navigationGroups) {
                        if ($groupKey === 'ungrouped') return 999;
                        return $navigationGroups[$groupKey]['sort_order'] ?? 999;
                    });
                @endphp

                <div class="space-y-6">
                    @foreach($groupedItems as $groupKey => $groupItems)
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900/50 dark:to-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                            {{-- Group Header --}}
                            <div class="bg-gradient-to-r from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 px-5 py-3 border-b border-slate-300 dark:border-slate-600">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                        <h3 class="font-bold text-slate-800 dark:text-slate-100">
                                            @if($groupKey === 'ungrouped')
                                                Csoportosítatlan
                                            @else
                                                {{ $navigationGroups[$groupKey]['label'] ?? $groupKey }}
                                            @endif
                                        </h3>
                                        <span class="px-2 py-0.5 bg-slate-600 dark:bg-slate-700 text-white text-xs font-medium rounded-full">
                                            {{ count($groupItems) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Group Items --}}
                            <div class="p-3 space-y-2">
                                @forelse($groupItems as $item)
                        <div
                            class="bg-white dark:bg-gray-800 border {{ in_array($item['resource_key'], $expandedItems) ? 'border-orange-200 dark:border-orange-800 shadow-md' : 'border-slate-200 dark:border-slate-700 shadow-sm' }} rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md"
                            wire:key="nav-item-{{ $item['resource_key'] }}">
                            
                            {{-- Item Header --}}
                            <div class="flex items-center justify-between {{ in_array($item['resource_key'], $expandedItems) ? 'bg-gradient-to-r from-orange-50/80 to-orange-100/50 dark:from-orange-950/30 dark:to-orange-900/20' : 'bg-slate-50/50 dark:bg-slate-900/20' }} px-5 py-4 cursor-pointer transition-all duration-200"
                                wire:click="toggleExpanded('{{ $item['resource_key'] }}')">
                                <div class="flex items-center gap-3">
                                    <div class="relative p-2.5 rounded-xl {{ in_array($item['resource_key'], $expandedItems) ? 'bg-orange-500 shadow-sm' : 'bg-white dark:bg-gray-800 shadow-sm border border-slate-200 dark:border-slate-700' }} transition-all duration-200">
                                        <svg class="w-5 h-5 {{ in_array($item['resource_key'], $expandedItems) ? 'text-white rotate-90' : 'text-slate-500 dark:text-slate-400' }} transition-all duration-300"
                                             fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="font-bold text-gray-900 dark:text-white text-base block">
                                            {{ $item['label'] }}
                                        </span>
                                        <div class="flex flex-col gap-1 mt-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Eredeti: {{ $item['default_label'] }}</span>
                                                @if($item['is_configured'])
                                                    <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-medium rounded-full">
                                                        Testreszabva
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $item['resource_class'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    {{-- Visibility Badge with Permission Info --}}
                                    @php
                                        $isVisible = $item['is_visible'] ?? false;
                                        $hasPermission = $item['has_permission'] ?? true;
                                        $configuredVisibility = $item['configured_visibility'] ?? true;
                                    @endphp

                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 {{ $isVisible ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                                        title="{{ $hasPermission ? 'Van jogosultság' : 'Nincs jogosultság' }}">
                                        @if($isVisible)
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Látható
                                        @else
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                            </svg>
                                            Rejtett
                                        @endif
                                    </span>

                                    {{-- Permission Badge (if no permission) --}}
                                    @if(!$hasPermission)
                                        <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                            title="Ez a szerepkör nem rendelkezik jogosultsággal ehhez a menüponthoz">
                                            <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            Nincs jog
                                        </span>
                                    @endif

                                    @if($item['is_configured'])
                                        <button
                                            wire:click.stop="resetToDefault('{{ $item['resource_key'] }}')"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                            title="Alaphelyzet visszaállítása">
                                            ↺ Reset
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Item Details (Expanded) --}}
                            @if(in_array($item['resource_key'], $expandedItems))
                                <div class="p-5 bg-white dark:bg-gray-800 space-y-4" @click="showSaved = true; setTimeout(() => showSaved = false, 2000)">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {{-- Label --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Címke
                                            </label>
                                            <input 
                                                type="text" 
                                                wire:model.blur="navigationItems.{{ array_search($item, $navigationItems) }}.label"
                                                wire:change="updateLabel('{{ $item['resource_key'] }}', $event.target.value)"
                                                placeholder="{{ $item['default_label'] }}"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Alapértelmezett: {{ $item['default_label'] }}</p>
                                        </div>

                                        {{-- Group --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Navigációs csoport
                                            </label>
                                            <select
                                                wire:change="updateGroup('{{ $item['resource_key'] }}', $event.target.value)"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                                <option value="">-- Nincs csoport --</option>
                                                @foreach($navigationGroups as $groupKey => $group)
                                                    <option value="{{ $groupKey }}" {{ $item['group'] === $groupKey ? 'selected' : '' }}>
                                                        {{ $group['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Sort Order --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Sorrend
                                            </label>
                                            <input 
                                                type="number" 
                                                wire:change="updateSortOrder('{{ $item['resource_key'] }}', $event.target.value)"
                                                value="{{ $item['sort_order'] }}"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minél kisebb, annál előrébb</p>
                                        </div>

                                        {{-- Visibility Toggle --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Láthatóság konfigurálása
                                            </label>
                                            <div class="space-y-2">
                                                <button
                                                    wire:click="toggleVisibility('{{ $item['resource_key'] }}')"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium text-sm transition-all duration-200 {{ $item['configured_visibility'] ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                                                    @if($item['configured_visibility'])
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        Látható (konfig)
                                                    @else
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                                        </svg>
                                                        Rejtett (konfig)
                                                    @endif
                                                </button>

                                                @if(!$item['has_permission'])
                                                    <div class="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                        </svg>
                                                        <div class="text-xs text-amber-800 dark:text-amber-200">
                                                            <strong>Figyelem:</strong> Ez a szerepkör nem rendelkezik jogosultsággal ehhez a menüponthoz.
                                                            A menüpont <strong>nem fog megjelenni</strong> a navigációban, még ha láthatóra van is állítva.
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                                @empty
                                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                                        <p class="text-gray-500 dark:text-gray-400">Nincs menüpont ebben a csoportban.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach

                    @if(count($groupedItems) === 0)
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                            <p class="text-gray-500 dark:text-gray-400">Nincs találat a keresésre.</p>
                        </div>
                    @endif
                </div>

                {{-- Live Preview --}}
                <div class="mt-6 bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
                    <h4 class="font-bold text-lg text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Élő Előnézet
                    </h4>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-slate-200 dark:border-slate-700">
                        @forelse($previewNavigation as $groupKey => $groupItems)
                            <div class="mb-4 last:mb-0">
                                @if($groupKey !== 'ungrouped' && isset($navigationGroups[$groupKey]))
                                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase px-3 py-2 tracking-wider">
                                        {{ $navigationGroups[$groupKey]['label'] }}
                                    </div>
                                @endif
                                <div class="space-y-1">
                                    @foreach($groupItems as $item)
                                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">Nincs látható menüpont</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 p-16 text-center">
                    <div class="max-w-md mx-auto">
                        <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 rounded-full mb-6">
                            <svg class="w-12 h-12 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                            Válassz egy szerepkört
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 text-lg">
                            Válaszd ki a bal oldali listából azt a szerepkört, amelyhez menüpontokat szeretnél beállítani.
                        </p>
                        <div class="mt-6 flex items-center justify-center gap-2 text-sm text-gray-400 dark:text-gray-500">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <span>Kattints a szerepkörre a részletek megtekintéséhez</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- New Group Modal --}}
    @if($showNewGroupModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showNewGroupModal') }" x-show="show">
            {{-- Background overlay --}}
            <div
                class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity z-0"
                @click="$wire.closeNewGroupModal()"
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            </div>

            {{-- Modal container --}}
            <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-8">
                {{-- Modal panel --}}
                <div
                    @click.stop
                    class="bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-[600px]"
                    x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 scale-95">
                    
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-orange-50 to-orange-100 dark:from-orange-950/30 dark:to-orange-900/20 px-6 py-4 border-b border-orange-200 dark:border-orange-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                    Új Navigációs Csoport
                                </h3>
                            </div>
                            <button 
                                wire:click="closeNewGroupModal"
                                type="button"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 space-y-4">
                        {{-- Group Key --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Kulcs <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="newGroupKey"
                                placeholder="pl. media-files"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            @error('newGroupKey')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Csak kisbetűk, számok és kötőjel. Példa: media-files, custom-tools</p>
                        </div>

                        {{-- Group Label --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Címke <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="newGroupLabel"
                                placeholder="pl. Média Fájlok"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            @error('newGroupLabel')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ez a menüben megjelenő név</p>
                        </div>

                        {{-- Sort Order --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Sorrend <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                wire:model="newGroupSortOrder"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                            @error('newGroupSortOrder')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minél kisebb, annál előrébb jelenik meg</p>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4 flex items-center justify-end gap-3">
                        <button
                            wire:click="closeNewGroupModal"
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                            Mégse
                        </button>
                        <button
                            wire:click="createNewGroup"
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Létrehozás
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Success Flash --}}
    @if(session()->has('group-created'))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed top-4 right-4 bg-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="font-medium">{{ session('group-created') }}</span>
        </div>
    @endif

    {{-- Manage Groups Modal --}}
    @if($showManageGroupsModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showManageGroupsModal') }" x-show="show">
            {{-- Background overlay --}}
            <div
                class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity z-0"
                @click="$wire.closeManageGroupsModal()"
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
            </div>

            {{-- Modal container --}}
            <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-8">
                {{-- Modal panel --}}
                <div
                    @click.stop
                    class="bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-[800px]"
                    x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 scale-95">

                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-950/30 dark:to-blue-900/20 px-6 py-4 border-b border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                        Navigációs Csoportok Kezelése
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $selectedRole ? ucfirst(str_replace('_', ' ', $selectedRole->name)) : 'Szerepkör' }}
                                    </p>
                                </div>
                            </div>
                            <button
                                wire:click="closeManageGroupsModal"
                                type="button"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                        @if(count($navigationGroups) > 0)
                            <div class="space-y-3">
                                @foreach($navigationGroups as $groupKey => $group)
                                    @php
                                        $groupModel = \App\Models\NavigationGroup::where('key', $groupKey)
                                            ->where(function($q) {
                                                $q->where('role_id', $this->selectedRoleId)
                                                  ->orWhereNull('role_id');
                                            })
                                            ->first();
                                    @endphp

                                    @if($groupModel)
                                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                            @if($editingGroupId === $groupModel->id)
                                                {{-- Edit Mode --}}
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Kulcs (nem szerkeszthető)
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value="{{ $editGroupKey }}"
                                                            disabled
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm cursor-not-allowed">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Címke
                                                        </label>
                                                        <input
                                                            type="text"
                                                            wire:model="editGroupLabel"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                        @error('editGroupLabel')
                                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                            Sorrend
                                                        </label>
                                                        <input
                                                            type="number"
                                                            wire:model="editGroupSortOrder"
                                                            min="0"
                                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                        @error('editGroupSortOrder')
                                                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                    <div class="flex items-center gap-2 pt-2">
                                                        <button
                                                            wire:click="saveEditedGroup({{ $groupModel->id }})"
                                                            type="button"
                                                            class="px-4 py-2 text-sm font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition-all">
                                                            Mentés
                                                        </button>
                                                        <button
                                                            wire:click="cancelEditGroup"
                                                            type="button"
                                                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                                                            Mégse
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                {{-- View Mode --}}
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-3">
                                                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $group['label'] }}</h4>
                                                            @if($groupModel->is_system)
                                                                <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 text-xs font-medium rounded-full">
                                                                    Rendszer
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-4 mt-1">
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">Kulcs: <code class="bg-gray-200 dark:bg-gray-600 px-1 rounded">{{ $groupKey }}</code></span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">Sorrend: {{ $groupModel->sort_order }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            wire:click="editGroup({{ $groupModel->id }})"
                                                            type="button"
                                                            class="px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-lg transition-all">
                                                            Szerkesztés
                                                        </button>
                                                        @if(!$groupModel->is_system)
                                                            <button
                                                                wire:click="deleteGroup({{ $groupModel->id }})"
                                                                wire:confirm="Biztosan törölni szeretnéd ezt a csoportot?"
                                                                type="button"
                                                                class="px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 rounded-lg transition-all">
                                                                Törlés
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">Nincs elérhető navigációs csoport.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4 flex items-center justify-end">
                        <button
                            wire:click="closeManageGroupsModal"
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                            Bezárás
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
