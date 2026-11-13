@foreach ($deals as $deal)
    <x-nest.deal-card :deal="$deal" />
@endforeach
@if ($deals->isEmpty())
    <x-nest.empty-state title="Tus ofertas guardadas ya no están disponibles" icon="bookmark" class="md:col-span-2 xl:col-span-3">
        <a href="{{ route('deals.index') }}" class="font-medium text-accent">Ver ofertas activas</a>
    </x-nest.empty-state>
@endif
