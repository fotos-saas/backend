<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        
        <x-filament::button type="submit" size="lg">
            Mentés
        </x-filament::button>
    </form>
</x-filament-panels::page>

