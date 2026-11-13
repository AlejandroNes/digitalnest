@extends('nest.layout')

@section('seo')
    <x-seo title="Buscar · DigitalNest" :noindex="true" />
@endsection

@section('content')
    <div class="flex flex-col gap-6">
        <x-nest.page-header title="Buscar">
            <form role="search" action="{{ route('search') }}" method="get" class="mt-3 flex h-12 items-center gap-2 rounded-full border border-line bg-surface pr-1.5 pl-4 focus-within:border-accent">
                <x-heroicon-o-magnifying-glass class="size-5 shrink-0 text-muted" />
                <label for="buscar-resultados" class="sr-only">Buscar</label>
                <input id="buscar-resultados" type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Apps, cupones, sectores…" @if ($term === '') autofocus @endif
                       class="min-w-0 flex-1 bg-transparent text-[16px] placeholder:text-muted focus:outline-none">
                <button type="submit" class="nest-pressable h-9 rounded-full bg-accent px-4 text-sm font-semibold text-accent-ink">Buscar</button>
            </form>
        </x-nest.page-header>

        @if ($term !== '' && mb_strlen($term) < 2)
            <p class="text-muted">Escribe al menos dos letras.</p>
        @elseif ($term !== '')
            <p class="text-sm text-muted" role="status">{{ trans_choice(':count resultado|:count resultados', $total, ['count' => $total]) }}</p>

            @if ($total === 0)
                <x-nest.empty-state title="No encontramos nada para «{{ $term }}»" icon="magnifying-glass">
                    Prueba con otra palabra o <a href="{{ route('explore') }}" class="font-medium text-accent">explora los sectores</a>.
                </x-nest.empty-state>
            @endif

            @if ($results['sectors']->isNotEmpty())
                <x-nest.section title="Sectores">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($results['sectors'] as $sector)
                            <x-nest.chip :href="route('sectors.show', ['slug' => $sector->slug])">{{ $sector->name }}</x-nest.chip>
                        @endforeach
                    </div>
                </x-nest.section>
            @endif

            @if ($results['deals']->isNotEmpty())
                <x-nest.section title="Ofertas">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($results['deals'] as $deal)
                            <x-nest.deal-card :deal="$deal" />
                        @endforeach
                    </div>
                </x-nest.section>
            @endif

            @if ($results['tools']->isNotEmpty())
                <x-nest.section title="Apps">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($results['tools'] as $tool)
                            <x-nest.app-card :tool="$tool" :href="route('apps.show', ['slug' => $tool->slug])" />
                        @endforeach
                    </div>
                </x-nest.section>
            @endif

            @php($others = collect([
                ...$results['rankings']->map(fn ($item) => ['Ranking', $item->title, route('rankings.show', ['slug' => $item->slug])]),
                ...$results['posts']->map(fn ($item) => ['Guía', $item->title, route('posts.show', ['slug' => $item->slug])]),
            ]))
            @if ($others->isNotEmpty())
                <x-nest.section title="Rankings y guías">
                    <div class="nest-card flex flex-col p-2">
                        @foreach ($others as [$kind, $title, $url])
                            <a href="{{ $url }}" class="flex items-center gap-3 rounded-xl p-3 hover:bg-raised">
                                <span class="w-20 shrink-0 text-xs font-medium text-muted">{{ $kind }}</span>
                                <span class="min-w-0 flex-1 font-medium">{{ $title }}</span>
                                <x-heroicon-o-chevron-right class="size-5 shrink-0 text-muted" />
                            </a>
                        @endforeach
                    </div>
                </x-nest.section>
            @endif
        @endif
    </div>
@endsection
