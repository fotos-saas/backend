<div class="space-y-4">
    @forelse($contacts as $contact)
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-user class="w-5 h-5 text-primary-500" />
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $contact->name ?? 'Névtelen' }}
                        </span>
                    </div>

                    @if($contact->phone)
                        <div class="flex items-center gap-2 mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <x-heroicon-m-phone class="w-4 h-4" />
                            <span>{{ $contact->phone }}</span>
                        </div>
                    @endif

                    @if($contact->email)
                        <div class="flex items-center gap-2 mt-1 text-sm text-gray-600 dark:text-gray-400">
                            <x-heroicon-m-envelope class="w-4 h-4" />
                            <span>{{ $contact->email }}</span>
                        </div>
                    @endif

                    @if($contact->note)
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400 italic border-l-2 border-warning-400 pl-2">
                            {{ $contact->note }}
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($contact->phone)
                        @php
                            $cleanPhone = preg_replace('/[^\d+]/', '', $contact->phone);
                            $smsUrl = \App\Helpers\SmsHelper::generateSmsLinkForContact($contact, $record);
                        @endphp

                        <a href="tel:{{ $cleanPhone }}"
                           class="outreach-action-btn inline-flex items-center justify-center p-2 rounded-lg text-success-600 hover:bg-success-50 dark:hover:bg-success-900/20 transition"
                           title="Hívás: {{ $contact->phone }}">
                            <x-heroicon-o-phone class="w-5 h-5" />
                        </a>

                        <a href="{{ $smsUrl }}"
                           class="outreach-action-btn inline-flex items-center justify-center p-2 rounded-lg text-info-600 hover:bg-info-50 dark:hover:bg-info-900/20 transition"
                           title="SMS küldése">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                        </a>
                    @endif
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
