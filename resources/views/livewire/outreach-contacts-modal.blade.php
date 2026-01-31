<div
    x-data
    x-on:open-phone-link.window="window.location.href = $event.detail.url"
>
    <div class="space-y-4">
        @forelse($contacts as $contact)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                    {{-- Bal oldal: Kontakt adatok --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <x-heroicon-m-user class="w-5 h-5 text-primary-500 flex-shrink-0" />
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ $contact->name ?? 'Névtelen' }}
                            </span>
                        </div>

                        @if($contact->phone)
                            <div class="flex items-center gap-2 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <x-heroicon-m-phone class="w-4 h-4 flex-shrink-0" />
                                <span>{{ $contact->phone }}</span>
                            </div>
                        @endif

                        @if($contact->email)
                            <div class="flex items-center gap-2 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <x-heroicon-m-envelope class="w-4 h-4 flex-shrink-0" />
                                <span>{{ $contact->email }}</span>
                            </div>
                        @endif

                        {{-- Statisztikák --}}
                        @if($contact->call_count > 0 || $contact->sms_count > 0)
                            <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
                                @if($contact->call_count > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                        <x-heroicon-m-phone class="w-3 h-3" />
                                        {{ $contact->call_count }}x hívás
                                    </span>
                                @endif
                                @if($contact->sms_count > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                        <x-heroicon-m-chat-bubble-left class="w-3 h-3" />
                                        {{ $contact->sms_count }}x SMS
                                    </span>
                                @endif
                                @if($contact->last_contacted_at)
                                    <span class="text-gray-500 dark:text-gray-400">
                                        Utoljára: {{ $contact->last_contacted_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Megjegyzés megjelenítés/szerkesztés --}}
                        @if($editingContactId === $contact->id)
                            <div class="mt-3">
                                <textarea
                                    wire:model.live="editingNote"
                                    rows="3"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                                    placeholder="Megjegyzés..."
                                    autofocus
                                ></textarea>
                                <div class="flex gap-2 mt-2">
                                    <button
                                        type="button"
                                        wire:click="saveNote"
                                        class="px-3 py-1.5 text-sm font-medium text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition"
                                    >
                                        Mentés
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditNote"
                                        class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-600 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition"
                                    >
                                        Mégse
                                    </button>
                                </div>
                            </div>
                        @elseif($contact->note)
                            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400 italic border-l-2 border-orange-400 pl-2">
                                {{ $contact->note }}
                            </div>
                        @endif
                    </div>

                    {{-- Jobb oldal: Akció gombok (mobilon alul, balra igazítva) --}}
                    <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                        @if($contact->phone)
                            {{-- Hívás gomb - zöld ikon --}}
                            <button
                                type="button"
                                wire:click="registerCall({{ $contact->id }})"
                                class="inline-flex items-center justify-center p-2 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition"
                                title="Hívás ({{ $contact->call_count }}x)"
                            >
                                <x-heroicon-o-phone class="w-6 h-6" style="color: #22c55e;" />
                            </button>

                            {{-- SMS gomb - kék ikon --}}
                            <button
                                type="button"
                                wire:click="registerSms({{ $contact->id }})"
                                class="inline-flex items-center justify-center p-2 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition"
                                title="SMS ({{ $contact->sms_count }}x)"
                            >
                                <x-heroicon-o-chat-bubble-left-ellipsis class="w-6 h-6" style="color: #3b82f6;" />
                            </button>
                        @endif

                        {{-- Megjegyzés gomb - narancs ikon --}}
                        <button
                            type="button"
                            wire:click="startEditNote({{ $contact->id }})"
                            class="inline-flex items-center justify-center p-2 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-lg transition"
                            title="Megjegyzés"
                        >
                            <x-heroicon-o-chat-bubble-bottom-center-text class="w-6 h-6" style="color: #f97316;" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-user-group class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <p>Nincsenek kapcsolattartók</p>
            </div>
        @endforelse
    </div>
</div>
