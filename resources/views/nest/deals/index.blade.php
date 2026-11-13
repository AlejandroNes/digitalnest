@extends('nest.layout')

@section('seo')
    <x-seo title="Ofertas y cupones de apps · DigitalNest" description="Cupones verificados y descuentos en apps de IA, vídeo, audio, hosting, VPN y más." :noindex="$filter !== null || $deals->currentPage() > 1" />
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header title="Ofertas" subtitle="Cupones y descuentos verificados a mano.">
            <div class="nest-scroller mt-3">
                <x-nest.chip :href="route('deals.index')" :active="$filter === null">Todas</x-nest.chip>
                @foreach (\App\Http\Controllers\DealController::FILTERS as $slug => $label)
                    <x-nest.chip :href="route('deals.index', ['filtro' => $slug])" :active="$filter === $slug">{{ $label }}</x-nest.chip>
                @endforeach
            </div>
        </x-nest.page-header>

        @if ($deals->isEmpty())
            <x-nest.empty-state title="No hay ofertas aquí" icon="ticket">Prueba con otro filtro o vuelve en unos días.</x-nest.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($deals as $deal)
                    <x-nest.deal-card :deal="$deal" />
                @endforeach
            </div>
            <x-nest.pagination :paginator="$deals" />
        @endif
    </div>
@endsection
