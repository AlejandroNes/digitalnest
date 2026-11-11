<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button class="w-full sm:w-auto" type="submit" wire:loading.attr="disabled" wire:target="save">
            Guardar configuración
        </x-filament::button>
    </form>
</x-filament-panels::page>
