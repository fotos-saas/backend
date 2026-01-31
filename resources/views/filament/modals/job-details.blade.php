<div class="p-6 space-y-6">
    <dl class="grid grid-cols-1 gap-6">
        {{-- Job Type --}}
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Job típus</dt>
            <dd class="text-base font-semibold text-gray-900">
                {{ class_basename($job['payload']['displayName'] ?? 'Unknown') }}
            </dd>
        </div>
        
        {{-- Queue --}}
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Queue</dt>
            <dd class="text-base text-gray-900">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $job['queue'] }}
                </span>
            </dd>
        </div>

        {{-- Status --}}
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Státusz</dt>
            <dd class="text-base text-gray-900">
                @php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'processing' => 'bg-blue-100 text-blue-800',
                        'completed' => 'bg-green-100 text-green-800',
                        'failed' => 'bg-red-100 text-red-800',
                    ];
                    $statusLabels = [
                        'pending' => 'Várakozik',
                        'processing' => 'Feldolgozás alatt',
                        'completed' => 'Kész',
                        'failed' => 'Sikertelen',
                    ];
                    $color = $statusColors[$job['status']] ?? 'bg-gray-100 text-gray-800';
                    $label = $statusLabels[$job['status']] ?? 'Ismeretlen';
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                    {{ $label }}
                </span>
            </dd>
        </div>

        {{-- Attempts --}}
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Próbálkozások</dt>
            <dd class="text-base text-gray-900">{{ $job['attempts'] ?? 0 }}</dd>
        </div>

        {{-- Created At --}}
        @if(isset($job['created_at']))
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Létrehozva</dt>
            <dd class="text-base text-gray-900">{{ \Carbon\Carbon::parse($job['created_at'])->format('Y-m-d H:i:s') }}</dd>
        </div>
        @endif

        {{-- Failed At --}}
        @if(isset($job['failed_at']))
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-gray-500 mb-2">Sikertelen időpont</dt>
            <dd class="text-base text-gray-900">{{ \Carbon\Carbon::parse($job['failed_at'])->format('Y-m-d H:i:s') }}</dd>
        </div>
        @endif
        
        {{-- Exception --}}
        @if(isset($job['exception']))
        <div class="border-b border-gray-200 pb-4">
            <dt class="text-sm font-medium text-red-500 mb-2">Hiba üzenet</dt>
            <dd class="text-sm text-gray-900">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 overflow-auto max-h-64">
                    <pre class="text-xs font-mono whitespace-pre-wrap break-words">{{ Str::limit($job['exception'], 1000) }}</pre>
                </div>
            </dd>
        </div>
        @endif
        
        {{-- Payload --}}
        <div>
            <dt class="text-sm font-medium text-gray-500 mb-2">Payload (JSON)</dt>
            <dd class="text-sm text-gray-900">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 overflow-auto max-h-96">
                    <pre class="text-xs font-mono whitespace-pre-wrap break-words">{{ json_encode($job['payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </dd>
        </div>
    </dl>
</div>

