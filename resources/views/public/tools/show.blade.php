@extends('public.tools.layout')

@section('seo')
    <x-seo :content="$tool" />
@endsection

@push('structured-data')
    @php($publisher = \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name'))
    <script type="application/ld+json">{!! json_encode($tool->structuredData(route('tools.show', ['slug' => $tool->slug]), $publisher), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <nav aria-label="Migas de pan">
        <a href="{{ route('tools.index') }}">Herramientas</a>
        @if($tool->category)
            › <a href="{{ route('tools.category', ['slug' => $tool->category->slug]) }}">{{ $tool->category->name }}</a>
        @endif
        › <span aria-current="page">{{ $tool->name }}</span>
    </nav>

    <header>
        <x-content-image :media="$tool->logo" :alt="$tool->logo?->model?->alt_text ?? ''" width="64" height="64" />
        <h1>{{ $tool->name }}</h1>
        <p>{{ $tool->tagline }}</p>
        @if($tool->rating)
            <p>Nota: <strong>{{ $tool->rating }}/10</strong></p>
        @endif
        @if($tool->outboundUrl())
            <a href="{{ $tool->outboundUrl() }}" target="_blank" rel="{{ $tool->isAffiliateLink() ? 'sponsored noopener' : 'noopener' }}">Visitar {{ $tool->name }}</a>
        @endif
        <p>
            @if($tool->tested_at)
                Probada el <time datetime="{{ $tool->tested_at->toDateString() }}">{{ $tool->tested_at->translatedFormat('j \d\e F \d\e Y') }}</time>
            @endif
            @if($tool->reviewed_at)
                · Actualizada el <time datetime="{{ $tool->reviewed_at->toDateString() }}">{{ $tool->reviewed_at->translatedFormat('j \d\e F \d\e Y') }}</time>
            @endif
        </p>
    </header>

    @if($tool->hasActiveDeal())
        <aside aria-label="Oferta">
            <p><strong>Oferta:</strong> {{ $tool->deal_notes }}
                @if($tool->deal_expires_at)
                    (hasta el {{ $tool->deal_expires_at->translatedFormat('j \d\e F') }})
                @endif
            </p>
        </aside>
    @endif

    <section aria-labelledby="datos">
        <h2 id="datos">Datos prácticos</h2>
        <dl>
            <dt>Precio</dt>
            <dd>
                {{ $tool->pricing_model?->getLabel() }}
                @if($tool->starting_price_usd !== null && ! $tool->pricing_model?->isFree())
                    · desde {{ number_format((float) $tool->starting_price_usd, 2) }} USD/mes
                @endif
            </dd>
            <dt>Plan gratis</dt>
            <dd>{{ $tool->has_free_plan ? 'Sí' : 'No' }}@if($tool->free_plan_notes) — {{ $tool->free_plan_notes }}@endif</dd>
            <dt>Pide tarjeta</dt>
            <dd>{{ $tool->requires_card ? 'Sí' : 'No' }}</dd>
            <dt>Español</dt>
            <dd>{{ $tool->spanish_support?->getLabel() }}</dd>
            <dt>Pagos desde Latinoamérica</dt>
            <dd>{{ $tool->latam_payments === null ? 'Sin verificar' : ($tool->latam_payments ? 'Sí' : 'No') }}</dd>
            @if($tool->platformList() !== [])
                <dt>Plataformas</dt>
                <dd>{{ implode(', ', array_map(fn ($platform) => $platform->getLabel(), $tool->platformList())) }}</dd>
            @endif
        </dl>
    </section>

    @if($tool->best_for || $tool->not_for)
        <section aria-labelledby="para-quien">
            <h2 id="para-quien">¿Para quién es?</h2>
            @if($tool->best_for)
                <p>{{ $tool->best_for }}</p>
            @endif
            @if($tool->not_for)
                <h3>No es para ti si…</h3>
                <p>{{ $tool->not_for }}</p>
            @endif
        </section>
    @endif

    @if($tool->image)
        <x-content-image :media="$tool->image" :alt="$tool->imageAlt() ?? ''" />
    @endif

    @if($tool->review)
        <section aria-labelledby="prueba">
            <h2 id="prueba">Nuestra prueba</h2>
            {!! \App\Services\ContentHtml::sanitize($tool->review) !!}
        </section>
    @endif

    @if($tool->prosList() !== [] || $tool->consList() !== [])
        <section aria-labelledby="pros-contras">
            <h2 id="pros-contras">Ventajas y desventajas</h2>
            @if($tool->prosList() !== [])
                <h3>Ventajas</h3>
                <ul>
                    @foreach($tool->prosList() as $pro)
                        <li>{{ $pro }}</li>
                    @endforeach
                </ul>
            @endif
            @if($tool->consList() !== [])
                <h3>Desventajas</h3>
                <ul>
                    @foreach($tool->consList() as $con)
                        <li>{{ $con }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if($tool->privacy_notes)
        <section aria-labelledby="privacidad">
            <h2 id="privacidad">Privacidad y datos</h2>
            <p>{{ $tool->privacy_notes }}</p>
        </section>
    @endif

    @if($alternatives->isNotEmpty())
        <section aria-labelledby="alternativas">
            <h2 id="alternativas">Alternativas</h2>
            @foreach($alternatives->take(3) as $alternative)
                @include('public.tools.card', ['tool' => $alternative])
            @endforeach
            <a href="{{ route('tools.alternatives', ['slug' => $tool->slug]) }}">Ver todas las alternativas a {{ $tool->name }}</a>
        </section>
    @endif
@endsection
