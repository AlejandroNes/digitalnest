@extends('nest.layout')

@section('seo')
    <x-seo fallbackDescription="Cupones verificados, descuentos y rankings de las mejores apps de IA, vídeo, audio, imágenes, hosting y más." />
@endsection

@section('content')
    <div class="flex flex-col gap-8 lg:gap-10">
        <section class="relative isolate overflow-hidden rounded-sheet border border-line bg-surface p-5 shadow-card lg:p-8">
            <div class="pointer-events-none absolute -top-24 -right-16 -z-10 size-72 rounded-full bg-accent/30 blur-3xl"></div>
            <div class="flex flex-col gap-4 lg:max-w-xl">
                <h1 class="text-[28px] leading-[1.1] font-bold tracking-tight lg:text-5xl">Las mejores apps, al mejor precio.</h1>
                <p class="text-muted">Cupones verificados y rankings honestos de IA, vídeo, audio, hosting y mucho más.</p>
                <form role="search" action="{{ route('search') }}" method="get" class="flex h-12 items-center gap-2 rounded-full border border-line bg-raised pr-1.5 pl-4 focus-within:border-accent">
                    <x-heroicon-o-magnifying-glass class="size-5 shrink-0 text-muted" />
                    <label for="home-search" class="sr-only">Buscar</label>
                    <input id="home-search" type="search" name="q" maxlength="100" placeholder="Busca una app o un cupón…" class="min-w-0 flex-1 bg-transparent text-[16px] placeholder:text-muted focus:outline-none">
                    <button type="submit" class="nest-pressable h-9 rounded-full bg-accent px-4 text-sm font-semibold text-accent-ink">Buscar</button>
                </form>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('deals.index') }}" class="nest-pressable inline-flex h-12 items-center rounded-full bg-accent px-6 font-semibold text-accent-ink">Ver ofertas</a>
                    <a href="{{ route('explore') }}" class="nest-pressable inline-flex h-12 items-center rounded-full border border-line bg-raised px-6 font-semibold">Explorar sectores</a>
                </div>
            </div>
        </section>

        @if ($expiringDeals->isNotEmpty())
            <x-nest.section title="Caducan pronto" :href="route('deals.index', ['filtro' => 'caducan-pronto'])">
                <div class="nest-scroller">
                    @foreach ($expiringDeals as $deal)
                        <x-nest.deal-card :deal="$deal" class="w-[85%] shrink-0 sm:w-80" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        @if ($sectors->isNotEmpty())
            <x-nest.section title="Sectores" :href="route('explore')">
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($sectors->take(12) as $sector)
                        <x-nest.sector-tile :sector="$sector" :href="route('sectors.show', ['slug' => $sector->slug])" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        <x-nest.section title="Ofertas destacadas" :href="route('deals.index')">
            @if ($featuredDeals->isEmpty())
                <x-nest.empty-state title="Pronto habrá ofertas" icon="ticket">Estamos verificando los primeros cupones.</x-nest.empty-state>
            @else
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($featuredDeals as $deal)
                        <x-nest.deal-card :deal="$deal" />
                    @endforeach
                </div>
            @endif
        </x-nest.section>

        <div class="grid gap-8 lg:grid-cols-2">
            @if ($ranking && $ranking->tools->isNotEmpty())
                <x-nest.section :title="$ranking->title">
                    <div class="nest-card flex flex-col p-2">
                        @foreach ($ranking->tools as $tool)
                            <x-nest.ranking-row :tool="$tool" :position="$loop->iteration" :href="route('apps.show', ['slug' => $tool->slug])" />
                        @endforeach
                    </div>
                </x-nest.section>
            @endif

            @if ($latestTools->isNotEmpty())
                <x-nest.section title="Apps nuevas">
                    <div class="flex flex-col gap-3">
                        @foreach ($latestTools as $tool)
                            <x-nest.app-card :tool="$tool" :href="route('apps.show', ['slug' => $tool->slug])" />
                        @endforeach
                    </div>
                </x-nest.section>
            @endif
        </div>
    </div>
@endsection
