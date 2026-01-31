<div style="display: flex; flex-wrap: wrap; gap: 16px;">
    @forelse($attachments as $attachment)
        @php
            $mediaId = $attachment['media_id'] ?? null;
            $fileName = $attachment['name'] ?? 'Ismeretlen';
            $media = $mediaId ? $project->getMedia('samples')->firstWhere('id', $mediaId) : null;
        @endphp

        @if($media)
            <div style="text-align: center;">
                <img
                    src="{{ $media->getUrl('thumb') }}"
                    onclick="event.stopPropagation(); event.preventDefault(); window.open('{{ $media->getUrl() }}', '_blank', 'width=1200,height=900'); return false;"
                    style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #e5e7eb; cursor: zoom-in;"
                    onmouseover="this.style.borderColor='#f59e0b'"
                    onmouseout="this.style.borderColor='#e5e7eb'"
                />
                <div style="font-size: 11px; color: #6b7280; margin-top: 6px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ $fileName }}
                </div>
            </div>
        @else
            <div style="text-align: center;">
                <div style="width: 100px; height: 100px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid #e5e7eb;">
                    <svg style="width: 32px; height: 32px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                    </svg>
                </div>
                <div style="font-size: 11px; color: #6b7280; margin-top: 6px; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ $fileName }}
                </div>
            </div>
        @endif
    @empty
        <span style="color: #9ca3af;">Nincs csatolmany</span>
    @endforelse
</div>
