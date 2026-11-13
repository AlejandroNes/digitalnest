@extends('nest.layout')

@php
    $tool = $deal->tool;
    $expired = $deal->isExpired();
@endphp

@section('seo')
    <x-seo :title="($deal->seo_title ?: $deal->title.' · '.$tool->name).' · DigitalNest'" :description="$deal->seo_description ?: $deal->description" :noindex="$expired" />
@endsection

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($deal->structuredData(route('deals.show', ['slug' => $deal->slug])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <x-nest.breadcrumbs :items="[['Ofertas', route('deals.index')], [$deal->title, route('deals.show', ['slug' => $deal->slug])]]" />
    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <a href="{{ route('deals.index') }}" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-muted hover:text-ink">
            <x-heroicon-o-chevron-left class="size-4" /> Ofertas
        </a>

        <article class="nest-card flex flex-col gap-5 p-5 lg:p-7">
            <a href="{{ route('apps.show', ['slug' => $tool->slug]) }}" class="flex items-center gap-3">
                <x-nest.app-logo :tool="$tool" size="size-14" />
                <span>
                    <span class="block font-semibold">{{ $tool->name }}</span>
                    <span class="text-sm text-muted">{{ $deal->type->getLabel() }}{{ $deal->is_exclusive ? " · Exclusiva" : "" }}</span>
                </span>
                @if ($deal->discount_percent)
                    <span class="nest-num ml-auto rounded-xl bg-deal-soft px-3 py-1.5 text-lg font-bold text-deal">−{{ $deal->discount_percent }}%</span>
                @endif
            </a>

            <h1 class="text-2xl leading-tight font-bold tracking-tight lg:text-3xl">{{ $deal->title }}</h1>

            @if ($expired)
                <p class="rounded-xl bg-hot/10 px-4 py-3 text-sm font-medium text-hot">Esta oferta caducó el {{ $deal->expires_at->translatedFormat('j \d\e F') }}. Abajo tienes alternativas activas.</p>
            @endif

            @if ($deal->deal_price !== null)
                <p class="flex items-baseline gap-2">
                    <span class="nest-num text-3xl font-bold">{{ $deal->priceLabel($deal->deal_price) }}</span>
                    @if ($deal->original_price)
                        <span class="nest-num text-muted line-through">{{ $deal->priceLabel($deal->original_price) }}</span>
                    @endif
                </p>
            @endif

            @if ($deal->description)
                <p class="text-muted">{{ $deal->description }}</p>
            @endif

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-raised p-3">
                    <dt class="text-muted">Caduca</dt>
                    <dd class="font-semibold">{{ $deal->expires_at?->translatedFormat('j M Y') ?? 'Sin fecha' }}</dd>
                </div>
                <div class="rounded-xl bg-raised p-3">
                    <dt class="text-muted">Verificada</dt>
                    <dd class="font-semibold">{{ $deal->verified_at?->diffForHumans() ?? 'Pendiente' }}</dd>
                </div>
            </dl>

            @unless ($expired)
                <div class="flex flex-col gap-2">
                    @if ($deal->hasCode())
                        <button type="button" data-copy-code="{{ $deal->code }}"
                                class="nest-pressable flex h-14 items-center justify-between rounded-2xl border border-dashed border-accent/50 bg-accent-soft px-5 font-mono text-lg font-semibold tracking-wider text-accent">
                            {{ $deal->code }}
                            <span class="flex items-center gap-1 font-sans text-sm tracking-normal"><x-heroicon-o-clipboard-document class="size-5" /> Copiar</span>
                        </button>
                    @endif
                    <a href="{{ route('deals.go', ['slug' => $deal->slug]) }}" target="_blank" rel="sponsored nofollow noopener"
                       class="nest-pressable flex h-14 items-center justify-center gap-2 rounded-2xl bg-accent text-base font-semibold text-accent-ink">
                        {{ $deal->hasCode() ? 'Ir a '.$tool->name : 'Activar oferta' }} <x-heroicon-o-arrow-up-right class="size-5" />
                    </a>
                </div>
            @endunless

            <p class="text-xs text-muted">Enlace de afiliado: podemos recibir una comisión sin coste para ti. <a href="{{ route('pages.affiliates') }}" class="underline">Más información</a>.</p>
        </article>

        @if ($related->isNotEmpty())
            <x-nest.section :title="$expired ? 'Alternativas activas' : 'Más ofertas parecidas'">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($related as $item)
                        <x-nest.deal-card :deal="$item" />
                    @endforeach
                </div>
            </x-nest.section>
        @endif
    </div>
@endsection
