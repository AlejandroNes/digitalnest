@extends('nest.layout')

@section('seo')
    <x-seo :content="$tool" />
@endsection

@push('structured-data')
    @php($publisher = \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name'))
    <script type="application/ld+json">{!! json_encode($tool->structuredData(route('apps.show', ['slug' => $tool->slug]), $publisher), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <x-nest.breadcrumbs :items="array_values(array_filter([
        $tool->category ? [$tool->category->name, route('sectors.show', ['slug' => $tool->category->slug])] : null,
        [$tool->name, route('apps.show', ['slug' => $tool->slug])],
    ]))" />
    <div class="flex flex-col gap-8">
        <header class="flex flex-col gap-5">
            @if ($tool->category)
                <a href="{{ route('sectors.show', ['slug' => $tool->category->slug]) }}" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-muted hover:text-ink">
                    <x-heroicon-o-chevron-left class="size-4" /> {{ $tool->category->name }}
                </a>
            @endif
            <div class="flex items-center gap-4">
                <x-nest.app-logo :tool="$tool" size="size-20" class="rounded-3xl text-2xl" />
                <div class="min-w-0">
                    <h1 class="text-[26px] leading-tight font-bold tracking-tight lg:text-4xl">{{ $tool->name }}</h1>
                    <p class="text-muted">{{ $tool->tagline }}</p>
                </div>
            </div>
            <div class="nest-scroller">
                @if ($tool->rating)
                    <span class="nest-card flex shrink-0 flex-col items-center px-4 py-2 text-center">
                        <span class="nest-num flex items-center gap-1 text-lg font-bold"><x-heroicon-s-star class="size-4 text-amber-400" />{{ $tool->rating }}/10</span>
                        <span class="text-xs text-muted">{{ $tool->verdict()['label'] }}</span>
                    </span>
                @endif
                <span class="nest-card flex shrink-0 flex-col items-center px-4 py-2 text-center">
                    <span class="nest-num text-lg font-bold">{{ $tool->startingPriceLabel() ?? 'Gratis' }}</span>
                    <span class="text-xs text-muted">{{ $tool->pricing_model?->getLabel() }}</span>
                </span>
                @foreach ($tool->platformList() as $platform)
                    <span class="nest-card flex shrink-0 items-center px-4 py-2 text-sm font-medium">{{ $platform->getLabel() }}</span>
                @endforeach
            </div>
            @if ($tool->outboundUrl())
                <a href="{{ $tool->outboundUrl() }}" target="_blank" rel="{{ $tool->isAffiliateLink() ? 'sponsored nofollow noopener' : 'noopener' }}"
                   class="nest-pressable flex h-12 items-center justify-center gap-2 rounded-full bg-accent font-semibold text-accent-ink sm:w-fit sm:px-8">
                    Visitar {{ $tool->name }} <x-heroicon-o-arrow-up-right class="size-5" />
                </a>
            @endif
        </header>

        @if ($deals->isNotEmpty())
            <x-nest.section title="Ofertas y cupones">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($deals as $deal)
                        <x-nest.deal-card :deal="$deal" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        @if ($tool->prosList() !== [] || $tool->consList() !== [])
            <div class="grid gap-3 md:grid-cols-2">
                @if ($tool->prosList() !== [])
                    <section class="nest-card flex flex-col gap-3 p-5">
                        <h2 class="font-semibold">Lo mejor</h2>
                        <ul class="flex flex-col gap-2 text-sm">
                            @foreach ($tool->prosList() as $pro)
                                <li class="flex gap-2"><x-heroicon-o-check-circle class="size-5 shrink-0 text-deal" />{{ $pro }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($tool->consList() !== [])
                    <section class="nest-card flex flex-col gap-3 p-5">
                        <h2 class="font-semibold">A tener en cuenta</h2>
                        <ul class="flex flex-col gap-2 text-sm">
                            @foreach ($tool->consList() as $con)
                                <li class="flex gap-2"><x-heroicon-o-minus-circle class="size-5 shrink-0 text-hot" />{{ $con }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        @endif

        @if ($tool->review)
            <x-nest.section title="Nuestra opinión">
                <div class="nest-card p-5 text-[15px] leading-relaxed text-muted [&_a]:text-accent [&_a]:underline [&_h2]:mt-4 [&_h2]:font-semibold [&_h2]:text-ink [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5">{!! $tool->review !!}</div>
            </x-nest.section>
        @endif

        @if ($alternatives->isNotEmpty())
            <x-nest.section title="Alternativas">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($alternatives as $alternative)
                        <x-nest.app-card :tool="$alternative" :href="route('apps.show', ['slug' => $alternative->slug])" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif

        @if ($tool->isAffiliateLink())
            <p class="text-xs text-muted">Algunos enlaces son de afiliado y están señalados. <a href="{{ route('pages.affiliates') }}" class="underline">No cambian nuestro veredicto</a>.</p>
        @endif
    </div>
@endsection
