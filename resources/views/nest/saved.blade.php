@extends('nest.layout')

@section('seo')
    <x-seo title="Guardados · DigitalNest" :noindex="true" />
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header title="Guardados" subtitle="Tus ofertas guardadas se quedan en este dispositivo; no necesitas cuenta." />

        <div data-saved-list data-source="{{ route('saved.cards') }}" data-state="loading" class="group/saved grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <x-nest.skeleton class="group-data-[state=loading]/saved:flex hidden" />
            <x-nest.skeleton class="group-data-[state=loading]/saved:flex hidden" />
            <x-nest.empty-state title="Aún no tienes guardados" icon="bookmark" class="hidden group-data-[state=empty]/saved:flex md:col-span-2 xl:col-span-3">
                Toca el marcador de una oferta para tenerla a mano. <a href="{{ route('deals.index') }}" class="font-medium text-accent">Ver ofertas</a>
            </x-nest.empty-state>
            <x-nest.empty-state title="No pudimos cargar tus guardados" icon="exclamation-circle" class="hidden group-data-[state=error]/saved:flex md:col-span-2 xl:col-span-3">
                Revisa tu conexión e inténtalo de nuevo.
            </x-nest.empty-state>
        </div>
        <noscript><p class="text-sm text-muted">Guardados necesita JavaScript activado.</p></noscript>
    </div>
@endsection
