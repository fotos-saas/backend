<div class="flex flex-col gap-6" x-data="{ 
    search: '', 
    showSaved: false,
    savingInProgress: false
}">
    {{-- Sticky Header with Actions --}}
    @if($selectedRole)
        <div class="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-700 shadow-sm -mx-6 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg flex items-center justify-center shadow-sm">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ ucfirst(str_replace('_', ' ', $selectedRole->name)) }}
                            </h2>
                            <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <span class="font-semibold text-orange-600 dark:text-orange-400">{{ count($rolePermissions) }}</span>
                                <span>aktív jogosultság</span>
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

                    {{-- Export Button --}}
                    <button
                        wire:click="exportConfiguration"
                        class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all shadow-sm hover:shadow-md">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export
                        </span>
                    </button>

                    {{-- Import Button --}}
                    <button
                        wire:click="openImportModal"
                        class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-green-500 to-green-600 rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-sm hover:shadow-md">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Import
                        </span>
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
                $totalPermissions = \Spatie\Permission\Models\Permission::count();
                $progressPercent = $totalPermissions > 0 ? (count($rolePermissions) / $totalPermissions * 100) : 0;
            @endphp
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                    <span>Jogosultságok: {{ count($rolePermissions) }} / {{ $totalPermissions }}</span>
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
                            @if($selectedRoleId === $role->id)
                                <div class="mt-2 pt-2 border-t border-orange-200/50 dark:border-orange-800/50">
                                    <div class="flex items-center gap-1.5 text-xs text-orange-700 dark:text-orange-300">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ $role->permissions()->count() }} jogosultság</span>
                                    </div>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Permissions Grid (Main Area) --}}
        <div class="lg:col-span-3">
            @if($selectedRole)
                {{-- Resources --}}
                <div class="space-y-3">
                    @foreach($resources as $resourceKey => $resource)
                        @php
                            $searchLower = strtolower($search ?? '');
                            $labelLower = strtolower($resource['label']);
                            $isVisible = empty($search) || str_contains($labelLower, $searchLower);
                        @endphp
                        
                        @if($isVisible)
                            <div
                                class="bg-white dark:bg-gray-800 border {{ in_array($resourceKey, $expandedGroups) ? 'border-orange-200 dark:border-orange-800 shadow-md' : 'border-slate-200 dark:border-slate-700 shadow-sm' }} rounded-xl overflow-hidden transition-all duration-300 hover:shadow-md"
                                x-data="{ expanded: {{ in_array($resourceKey, $expandedGroups) ? 'true' : 'false' }} }">
                                {{-- Resource Header --}}
                                <div
                                    class="flex items-center justify-between {{ in_array($resourceKey, $expandedGroups) ? 'bg-gradient-to-r from-orange-50/80 to-orange-100/50 dark:from-orange-950/30 dark:to-orange-900/20' : 'bg-slate-50/50 dark:bg-slate-900/20' }} px-5 py-4 cursor-pointer transition-all duration-200 hover:from-orange-50/80 hover:to-orange-100/50 dark:hover:from-orange-950/30 dark:hover:to-orange-900/20"
                                    wire:click="toggleExpanded('{{ $resourceKey }}')">
                                    <div class="flex items-center gap-3">
                                        <div class="relative p-2.5 rounded-xl {{ in_array($resourceKey, $expandedGroups) ? 'bg-orange-500 shadow-sm' : 'bg-white dark:bg-gray-800 shadow-sm border border-slate-200 dark:border-slate-700' }} transition-all duration-200">
                                            <svg class="w-5 h-5 {{ in_array($resourceKey, $expandedGroups) ? 'text-white rotate-90' : 'text-slate-500 dark:text-slate-400' }} transition-all duration-300"
                                                 fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="font-bold text-gray-900 dark:text-white text-base block">
                                                {{ $resource['label'] }}
                                            </span>
                                            <div class="flex flex-col gap-1 mt-0.5">
                                                <div class="flex items-center gap-2">
                                                    @php
                                                        $resourcePerms = 0;
                                                        $totalPerms = 0;
                                                        
                                                        if (isset($resource['permissions'])) {
                                                            $totalPerms += count($resource['permissions']);
                                                            foreach (array_keys($resource['permissions']) as $permKey) {
                                                                if ($this->hasPermission("{$resourceKey}.{$permKey}")) $resourcePerms++;
                                                            }
                                                        }
                                                        if (isset($resource['tabs'])) {
                                                            $totalPerms += count($resource['tabs']);
                                                            foreach (array_keys($resource['tabs']) as $tabKey) {
                                                                if ($this->hasPermission("{$resourceKey}.tab.{$tabKey}")) $resourcePerms++;
                                                            }
                                                        }
                                                        if (isset($resource['actions'])) {
                                                            $totalPerms += count($resource['actions']);
                                                            foreach (array_keys($resource['actions']) as $actionKey) {
                                                                if ($this->hasPermission("{$resourceKey}.action.{$actionKey}")) $resourcePerms++;
                                                            }
                                                        }
                                                        if (isset($resource['relations'])) {
                                                            $totalPerms += count($resource['relations']);
                                                            foreach (array_keys($resource['relations']) as $relKey) {
                                                                if ($this->hasPermission("{$resourceKey}.relation.{$relKey}")) $resourcePerms++;
                                                            }
                                                        }
                                                    @endphp
                                                    <span class="text-xs font-semibold {{ $resourcePerms > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-500 dark:text-slate-500' }}">
                                                        {{ $resourcePerms }}/{{ $totalPerms }} aktív
                                                    </span>
                                                    @if($resourcePerms > 0 && $resourcePerms < $totalPerms)
                                                        <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-medium rounded-full">
                                                            Részleges
                                                        </span>
                                                    @elseif($resourcePerms === $totalPerms && $totalPerms > 0)
                                                        <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-medium rounded-full">
                                                            Teljes
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $resource['namespace'] ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        {{-- Visibility Badge --}}
                                        @php
                                            $isVisible = $resource['is_visible'] ?? false;
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 {{ $isVisible ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                                            title="{{ $isVisible ? 'Látható a navigációban' : 'Rejtett a navigációban' }}">
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
                                        
                                        {{-- Mind Toggle Button --}}
                                        <button
                                            wire:click.stop="toggleResourceAll('{{ $resourceKey }}')"
                                            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all duration-200 {{ $this->resourceHasAllPermissions($resourceKey) ? 'bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-400 dark:hover:bg-rose-950/50 border border-rose-200 dark:border-rose-800' : 'bg-orange-500 text-white hover:bg-orange-600 shadow-sm hover:shadow-md' }}">
                                            {{ $this->resourceHasAllPermissions($resourceKey) ? '✕ Törlés' : '✓ Mind' }}
                                        </button>
                                    </div>
                                </div>

                                {{-- Resource Permissions --}}
                                @if(in_array($resourceKey, $expandedGroups))
                                    <div class="p-5 bg-white dark:bg-gray-800 space-y-6">
                                        {{-- Basic CRUD Permissions --}}
                                        @if(isset($resource['permissions']) && count($resource['permissions']) > 0)
                                            <div class="bg-gradient-to-br from-sky-50/50 to-sky-100/30 dark:from-sky-950/20 dark:to-sky-900/10 rounded-xl p-4 border border-sky-200/60 dark:border-sky-800/40">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="p-1.5 bg-sky-500/90 rounded-lg shadow-sm">
                                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        </svg>
                                                    </div>
                                                    <h5 class="text-sm font-bold text-sky-800 dark:text-sky-200">
                                                        CRUD Műveletek
                                                    </h5>
                                                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 rounded-full">
                                                        {{ count($resource['permissions']) }}
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    @foreach($resource['permissions'] as $permKey => $permLabel)
                                                        <label
                                                            class="group relative flex items-center gap-2 p-2.5 rounded-lg bg-white dark:bg-gray-800 border {{ $this->hasPermission("{$resourceKey}.{$permKey}") ? 'border-orange-300 dark:border-orange-600 ring-1 ring-orange-200/50 dark:ring-orange-800/50' : 'border-slate-200 dark:border-slate-700' }} cursor-pointer transition-all duration-150 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-sm"
                                                            @click="showSaved = true; setTimeout(() => showSaved = false, 2000)">
                                                            <input
                                                                type="checkbox"
                                                                wire:click="togglePermission('{{ $resourceKey }}.{{ $permKey }}')"
                                                                {{ $this->hasPermission("{$resourceKey}.{$permKey}") ? 'checked' : '' }}
                                                                class="sr-only peer">
                                                            <div class="relative w-4 h-4 rounded {{ $this->hasPermission("{$resourceKey}.{$permKey}") ? 'bg-orange-500' : 'bg-slate-200 dark:bg-slate-700' }} transition-all duration-150 flex items-center justify-center flex-shrink-0">
                                                                @if($this->hasPermission("{$resourceKey}.{$permKey}"))
                                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                            <span class="text-xs font-medium {{ $this->hasPermission("{$resourceKey}.{$permKey}") ? 'text-orange-700 dark:text-orange-300' : 'text-slate-600 dark:text-slate-400' }} transition-colors">{{ $permLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Tab Permissions --}}
                                        @if(isset($resource['tabs']) && count($resource['tabs']) > 0)
                                            <div class="bg-gradient-to-br from-violet-50/50 to-violet-100/30 dark:from-violet-950/20 dark:to-violet-900/10 rounded-xl p-4 border border-violet-200/60 dark:border-violet-800/40">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="p-1.5 bg-violet-500/90 rounded-lg shadow-sm">
                                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                    </div>
                                                    <h5 class="text-sm font-bold text-violet-800 dark:text-violet-200">
                                                        Tab-ok
                                                    </h5>
                                                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-300 rounded-full">
                                                        {{ count($resource['tabs']) }}
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                    @foreach($resource['tabs'] as $tabKey => $tabLabel)
                                                        <label
                                                            class="group relative flex items-center gap-2 p-2.5 rounded-lg bg-white dark:bg-gray-800 border {{ $this->hasPermission("{$resourceKey}.tab.{$tabKey}") ? 'border-orange-300 dark:border-orange-600 ring-1 ring-orange-200/50 dark:ring-orange-800/50' : 'border-slate-200 dark:border-slate-700' }} cursor-pointer transition-all duration-150 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-sm"
                                                            @click="showSaved = true; setTimeout(() => showSaved = false, 2000)">
                                                            <input
                                                                type="checkbox"
                                                                wire:click="togglePermission('{{ $resourceKey }}.tab.{{ $tabKey }}')"
                                                                {{ $this->hasPermission("{$resourceKey}.tab.{$tabKey}") ? 'checked' : '' }}
                                                                class="sr-only peer">
                                                            <div class="relative w-4 h-4 rounded {{ $this->hasPermission("{$resourceKey}.tab.{$tabKey}") ? 'bg-orange-500' : 'bg-slate-200 dark:bg-slate-700' }} transition-all duration-150 flex items-center justify-center flex-shrink-0">
                                                                @if($this->hasPermission("{$resourceKey}.tab.{$tabKey}"))
                                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                            <span class="text-xs font-medium {{ $this->hasPermission("{$resourceKey}.tab.{$tabKey}") ? 'text-orange-700 dark:text-orange-300' : 'text-slate-600 dark:text-slate-400' }} transition-colors">{{ $tabLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Action Permissions --}}
                                        @if(isset($resource['actions']) && count($resource['actions']) > 0)
                                            <div class="bg-gradient-to-br from-emerald-50/50 to-emerald-100/30 dark:from-emerald-950/20 dark:to-emerald-900/10 rounded-xl p-4 border border-emerald-200/60 dark:border-emerald-800/40">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="p-1.5 bg-emerald-500/90 rounded-lg shadow-sm">
                                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                        </svg>
                                                    </div>
                                                    <h5 class="text-sm font-bold text-emerald-800 dark:text-emerald-200">
                                                        Műveletek
                                                    </h5>
                                                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 rounded-full">
                                                        {{ count($resource['actions']) }}
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                    @foreach($resource['actions'] as $actionKey => $actionLabel)
                                                        <label
                                                            class="group relative flex items-center gap-2 p-2.5 rounded-lg bg-white dark:bg-gray-800 border {{ $this->hasPermission("{$resourceKey}.action.{$actionKey}") ? 'border-orange-300 dark:border-orange-600 ring-1 ring-orange-200/50 dark:ring-orange-800/50' : 'border-slate-200 dark:border-slate-700' }} cursor-pointer transition-all duration-150 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-sm"
                                                            @click="showSaved = true; setTimeout(() => showSaved = false, 2000)">
                                                            <input
                                                                type="checkbox"
                                                                wire:click="togglePermission('{{ $resourceKey }}.action.{{ $actionKey }}')"
                                                                {{ $this->hasPermission("{$resourceKey}.action.{$actionKey}") ? 'checked' : '' }}
                                                                class="sr-only peer">
                                                            <div class="relative w-4 h-4 rounded {{ $this->hasPermission("{$resourceKey}.action.{$actionKey}") ? 'bg-orange-500' : 'bg-slate-200 dark:bg-slate-700' }} transition-all duration-150 flex items-center justify-center flex-shrink-0">
                                                                @if($this->hasPermission("{$resourceKey}.action.{$actionKey}"))
                                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                            <span class="text-xs font-medium {{ $this->hasPermission("{$resourceKey}.action.{$actionKey}") ? 'text-orange-700 dark:text-orange-300' : 'text-slate-600 dark:text-slate-400' }} transition-colors">{{ $actionLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Relation Permissions --}}
                                        @if(isset($resource['relations']) && count($resource['relations']) > 0)
                                            <div class="bg-gradient-to-br from-amber-50/50 to-amber-100/30 dark:from-amber-950/20 dark:to-amber-900/10 rounded-xl p-4 border border-amber-200/60 dark:border-amber-800/40">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="p-1.5 bg-amber-500/90 rounded-lg shadow-sm">
                                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                                        </svg>
                                                    </div>
                                                    <h5 class="text-sm font-bold text-amber-800 dark:text-amber-200">
                                                        Kapcsolatok
                                                    </h5>
                                                    <span class="ml-auto text-xs font-semibold px-2 py-0.5 bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded-full">
                                                        {{ count($resource['relations']) }}
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                    @foreach($resource['relations'] as $relationKey => $relationLabel)
                                                        <label
                                                            class="group relative flex items-center gap-2 p-2.5 rounded-lg bg-white dark:bg-gray-800 border {{ $this->hasPermission("{$resourceKey}.relation.{$relationKey}") ? 'border-orange-300 dark:border-orange-600 ring-1 ring-orange-200/50 dark:ring-orange-800/50' : 'border-slate-200 dark:border-slate-700' }} cursor-pointer transition-all duration-150 hover:border-orange-300 dark:hover:border-orange-700 hover:shadow-sm"
                                                            @click="showSaved = true; setTimeout(() => showSaved = false, 2000)">
                                                            <input
                                                                type="checkbox"
                                                                wire:click="togglePermission('{{ $resourceKey }}.relation.{{ $relationKey }}')"
                                                                {{ $this->hasPermission("{$resourceKey}.relation.{$relationKey}") ? 'checked' : '' }}
                                                                class="sr-only peer">
                                                            <div class="relative w-4 h-4 rounded {{ $this->hasPermission("{$resourceKey}.relation.{$relationKey}") ? 'bg-orange-500' : 'bg-slate-200 dark:bg-slate-700' }} transition-all duration-150 flex items-center justify-center flex-shrink-0">
                                                                @if($this->hasPermission("{$resourceKey}.relation.{$relationKey}"))
                                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                            <span class="text-xs font-medium {{ $this->hasPermission("{$resourceKey}.relation.{$relationKey}") ? 'text-orange-700 dark:text-orange-300' : 'text-slate-600 dark:text-slate-400' }} transition-colors">{{ $relationLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 p-16 text-center">
                    <div class="max-w-md mx-auto">
                        <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 rounded-full mb-6">
                            <svg class="w-12 h-12 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                            Válassz egy szerepkört
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 text-lg">
                            Válaszd ki a bal oldali listából azt a szerepkört, amelyhez jogosultságokat szeretnél beállítani.
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

    {{-- Import Modal --}}
    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ closing: false }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop --}}
                <div
                    class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-80"
                    @click="$wire.closeImportModal()"
                    x-show="!closing"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                </div>

                {{-- Modal --}}
                <div
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                    x-show="!closing"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    @click.away="$wire.closeImportModal()">

                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-white/20 rounded-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-white">
                                        Konfiguráció Importálása
                                    </h3>
                                    <p class="text-green-100 text-sm">
                                        {{ ucfirst(str_replace('_', ' ', $selectedRole->name ?? '')) }} szerepkörhöz
                                    </p>
                                </div>
                            </div>
                            <button
                                wire:click="closeImportModal"
                                class="text-white/80 hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 space-y-4">
                        {{-- File Upload --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                JSON Fájl Feltöltése
                            </label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="font-semibold">Kattints a feltöltéshez</span> vagy húzd ide a fájlt
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">JSON (MAX. 2MB)</p>
                                    </div>
                                    <input
                                        type="file"
                                        wire:model="importFile"
                                        accept=".json,application/json"
                                        class="hidden" />
                                </label>
                            </div>
                            @if($importFile)
                                <p class="mt-2 text-sm text-green-600 dark:text-green-400 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Fájl kiválasztva: {{ $importFile->getClientOriginalName() }}
                                </p>
                            @endif
                            @error('importFile')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Merge Mode Checkbox --}}
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="importMergeMode"
                                    class="mt-1 w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div>
                                    <span class="text-sm font-semibold text-blue-900 dark:text-blue-100">Egyesítés mód (Merge)</span>
                                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                        Ha be van kapcsolva, a meglévő beállítások megmaradnak és kiegészülnek az újjakkal.
                                        Ha ki van kapcsolva, a meglévő beállítások törlődnek és felülíródnak.
                                    </p>
                                </div>
                            </label>
                        </div>

                        {{-- Import Result --}}
                        @if(!empty($importResult))
                            <div class="rounded-lg p-4 {{ $importResult['success'] ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                                <div class="flex items-start gap-3">
                                    @if($importResult['success'])
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-2">
                                                Sikeres importálás!
                                            </h4>
                                            @if(isset($importResult['messages']) && count($importResult['messages']) > 0)
                                                <ul class="text-xs text-green-700 dark:text-green-300 space-y-1">
                                                    @foreach($importResult['messages'] as $message)
                                                        <li>• {{ $message }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @else
                                        <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        <div class="flex-1">
                                            <h4 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-2">
                                                Importálási hiba
                                            </h4>
                                            @if(isset($importResult['errors']) && count($importResult['errors']) > 0)
                                                <ul class="text-xs text-red-700 dark:text-red-300 space-y-1">
                                                    @foreach($importResult['errors'] as $error)
                                                        <li>• {{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 flex items-center justify-end gap-3">
                        <button
                            wire:click="closeImportModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
                            Mégse
                        </button>
                        <button
                            wire:click="importConfiguration"
                            class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-green-500 to-green-600 rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                            {{ !$importFile ? 'disabled' : '' }}>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Importálás
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Auto-close modal after successful import --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('import-complete', () => {
                setTimeout(() => {
                    @this.closeImportModal();
                }, 2000);
            });
        });
    </script>
</div>

