@extends('nest.layout')

@section('seo')
    <x-seo :title="($sector->seo_title ?: $sector->name.': mejores apps y ofertas').' · DigitalNest'" :description="$sector->seo_description ?: $sector->description" :noindex="$sector->seo_noindex" />
@endsection

@section('content')
    <x-nest.breadcrumbs :items="array_values(array_filter([
        ['Explorar', route('explore')],
        $sector->parent ? [$sector->parent->name, route('sectors.show', ['slug' => $sector->parent->slug])] : null,
        [$sector->name, route('sectors.show', ['slug' => $sector->slug])],
    ]))" />
    <div class="flex flex-col gap-8" style="--sector: {{ $sector->accent_color ?? $sector->parent?->accent_color ?? 'var(--nest-accent)' }}">
        <x-nest.page-header :title="$sector->name" :subtitle="$sector->description"
            :back="$sector->parent ? ['url' => route('sectors.show', ['slug' => $sector->parent->slug]), 'label' => $sector->parent->name] : ['url' => route('explore'), 'label' => 'Explorar']">
            @if ($sector->children->isNotEmpty())
                <div class="nest-scroller mt-3">
                    @foreach ($sector->children as $child)
                        <x-nest.chip :href="route('sectors.show', ['slug' => $child->slug])">{{ $child->name }}</x-nest.chip>
                    @endforeach
                </div>
            @endif
        </x-nest.page-header>

        @if ($deals->isNotEmpty())
            <x-nest.section title="Ofertas activas">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($deals as $deal)
                        <x-nest.deal-card :deal="$deal" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        @if ($rankings->isNotEmpty())
            <x-nest.section title="Rankings">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($rankings as $ranking)
                        <div class="nest-card flex items-center gap-3 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-400/15 text-amber-500"><x-heroicon-o-trophy class="size-6" /></span>
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ $ranking->title }}</span>
                                <span class="text-sm text-muted">{{ $ranking->tools_count }} apps</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        <x-nest.section title="Todas las apps">
            @if ($tools->isEmpty())
                <x-nest.empty-state title="Aún no hay apps en este sector">Estamos probando las primeras.</x-nest.empty-state>
            @else
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($tools as $tool)
                        <x-nest.app-card :tool="$tool" :href="route('apps.show', ['slug' => $tool->slug])" />
                    @endforeach
                </div>
                <x-nest.pagination :paginator="$tools" />
            @endif
        </x-nest.section>
    </div>
@endsection
