@extends('nest.layout')

@section('seo')
    <x-seo title="Explorar sectores · DigitalNest" description="Encuentra apps y ofertas por sector: IA, vídeo, audio, imágenes, hosting, VPN, productividad y más." />
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header title="Explorar" subtitle="Elige un sector para ver sus mejores apps y ofertas." />

        @forelse ($sectors as $sector)
            <section class="nest-card flex flex-col gap-3 p-4" style="--sector: {{ $sector->accent_color ?? 'var(--nest-accent)' }}">
                <a href="{{ route('sectors.show', ['slug' => $sector->slug]) }}" class="group flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-[color-mix(in_oklab,var(--sector)_16%,transparent)] text-(--sector)">
                        <x-dynamic-component :component="'heroicon-o-'.($sector->icon ?: 'squares-2x2')" class="size-6" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold group-hover:text-accent">{{ $sector->name }}</span>
                        @if ($sector->description)
                            <span class="block truncate text-sm text-muted">{{ $sector->description }}</span>
                        @endif
                    </span>
                    <x-heroicon-o-chevron-right class="size-5 shrink-0 text-muted" />
                </a>
                @if ($sector->children->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sector->children as $child)
                            <x-nest.chip :href="route('sectors.show', ['slug' => $child->slug])">{{ $child->name }}</x-nest.chip>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <x-nest.empty-state title="Aún no hay sectores">Vuelve pronto.</x-nest.empty-state>
        @endforelse
    </div>
@endsection
