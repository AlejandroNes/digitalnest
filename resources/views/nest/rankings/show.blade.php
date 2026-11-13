@extends('nest.layout')

@section('seo')
    <x-seo :title="($ranking->seo_title ?: $ranking->title).' · DigitalNest'" :description="$ranking->seo_description ?: \Illuminate\Support\Str::limit((string) $ranking->intro, 155)" :noindex="$ranking->seo_noindex" />
@endsection

@push('structured-data')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $ranking->title,
        'itemListElement' => $ranking->tools->values()->map(fn ($tool, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $tool->name,
            'url' => route('apps.show', ['slug' => $tool->slug]),
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <x-nest.breadcrumbs :items="[['Rankings', route('rankings.index')], [$ranking->title, route('rankings.show', ['slug' => $ranking->slug])]]" />
    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <x-nest.page-header :title="$ranking->title" :subtitle="$ranking->intro"
            :back="$ranking->category ? ['url' => route('sectors.show', ['slug' => $ranking->category->slug]), 'label' => $ranking->category->name] : ['url' => route('rankings.index'), 'label' => 'Rankings']" />

        <ol class="flex flex-col gap-3">
            @forelse ($ranking->tools as $tool)
                @php($deal = $tool->deals->first())
                <li class="nest-card flex flex-col gap-3 p-4">
                    <a href="{{ route('apps.show', ['slug' => $tool->slug]) }}" class="flex items-center gap-3">
                        <span @class([
                            'nest-num grid size-9 shrink-0 place-items-center rounded-full text-sm font-bold',
                            'bg-amber-400/15 text-amber-500' => $loop->first,
                            'bg-raised text-muted' => ! $loop->first,
                        ])>{{ $loop->iteration }}</span>
                        <x-nest.app-logo :tool="$tool" />
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $tool->name }}</span>
                            <span class="block truncate text-sm text-muted">{{ $tool->pivot->best_for ? 'Mejor para: '.$tool->pivot->best_for : $tool->tagline }}</span>
                        </span>
                        @if ($tool->rating)
                            <span class="nest-num flex shrink-0 items-center gap-1 text-sm font-semibold"><x-heroicon-s-star class="size-4 text-amber-400" />{{ $tool->rating }}</span>
                        @endif
                    </a>
                    @if ($tool->pivot->verdict)
                        <p class="text-sm text-muted">{{ $tool->pivot->verdict }}</p>
                    @endif
                    @if ($deal)
                        <a href="{{ route('deals.show', ['slug' => $deal->slug]) }}" class="nest-pressable flex items-center gap-2 rounded-xl bg-deal-soft px-3 py-2.5 text-sm font-semibold text-deal">
                            <x-heroicon-o-ticket class="size-5 shrink-0" />
                            <span class="min-w-0 flex-1 truncate">{{ $deal->title }}</span>
                            @if ($deal->discount_percent)<span class="nest-num">−{{ $deal->discount_percent }}%</span>@endif
                        </a>
                    @endif
                </li>
            @empty
                <x-nest.empty-state title="Este ranking aún está vacío" icon="trophy">Vuelve pronto.</x-nest.empty-state>
            @endforelse
        </ol>
    </div>
@endsection
