@extends('public.layout')

@section('seo')
    <x-seo :content="$tool" />
@endsection

@push('structured-data')
    @php($publisher = \App\Models\GeneralSetting::current(false)->company_name ?: config('app.name'))
    <script type="application/ld+json">{!! json_encode($tool->structuredData(route('tools.show', ['slug' => $tool->slug]), $publisher), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    @php($verdict = $tool->verdict())
    <div class="dn-wrap">
        <nav class="dn-crumbs" aria-label="Migas de pan" style="padding-bottom:1.25rem">
            <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
            <a href="{{ route('tools.index') }}">Herramientas</a> <span aria-hidden="true">/</span>
            @if($tool->category)
                <a href="{{ route('tools.category', ['slug' => $tool->category->slug]) }}">{{ $tool->category->name }}</a> <span aria-hidden="true">/</span>
            @endif
            <span aria-current="page">{{ $tool->name }}</span>
        </nav>

        <article class="dn-panel">
            <header class="dn-report-head">
                <div>
                    <div class="dn-report-meta">
                        @include('public.partials.logo', ['tool' => $tool, 'large' => true])
                        <div>
                            @if($tool->category)
                                <a class="dn-link dn-small" href="{{ route('tools.category', ['slug' => $tool->category->slug]) }}">{{ $tool->category->name }}</a>
                            @endif
                            @if($tool->tested_at)
                                <p class="dn-soft dn-small">Probada el <time datetime="{{ $tool->tested_at->toDateString() }}">{{ $tool->tested_at->translatedFormat('j \d\e F \d\e Y') }}</time></p>
                            @endif
                        </div>
                        @if($tool->is_featured)
                            <span class="dn-tag">Recomendada</span>
                        @endif
                    </div>
                    <h1 class="dn-display dn-title" style="margin-top:1.25rem">{{ $tool->name }}</h1>
                    <p class="dn-lede">{{ $tool->tagline }}</p>
                    @if($tool->outboundUrl())
                        <div class="dn-actions">
                            <a class="dn-btn" href="{{ $tool->outboundUrl() }}" target="_blank" rel="{{ $tool->isAffiliateLink() ? 'sponsored noopener' : 'noopener' }}">
                                Ir a {{ $tool->name }} @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])
                            </a>
                            @if($tool->isAffiliateLink())
                                <span class="dn-sponsor-note">Enlace de afiliado: si contratas, recibimos una comisión sin coste extra para ti.</span>
                            @endif
                        </div>
                    @endif
                </div>
                @if($verdict)
                    <div class="dn-verdict dn-tone-{{ $verdict['tone'] }}" role="img" aria-label="Nota {{ $tool->rating }} de 10, {{ mb_strtolower($verdict['label']) }}">
                        <span class="dn-verdict-score" aria-hidden="true">{{ $tool->rating }}<small>/10</small></span>
                        <span class="dn-verdict-label" aria-hidden="true">{{ $verdict['label'] }}</span>
                        <span class="dn-verdict-note" aria-hidden="true">Nota DigitalNest</span>
                    </div>
                @endif
            </header>

            @if($tool->hasActiveDeal())
                <p class="dn-deal">
                    <strong>Oferta:</strong> {{ $tool->deal_notes }}
                    @if($tool->deal_expires_at)
                        <span style="font-weight:500">· hasta el {{ $tool->deal_expires_at->translatedFormat('j \d\e F') }}</span>
                    @endif
                </p>
            @endif

            <dl class="dn-specs" aria-label="Datos de la prueba">
                <div class="dn-spec">
                    <dt>Precio</dt>
                    <dd>
                        {{ $tool->startingPriceLabel() ? 'Desde '.$tool->startingPriceLabel().' USD al mes' : $tool->pricing_model?->getLabel() }}
                        <small>{{ $tool->pricing_model?->getLabel() }}</small>
                    </dd>
                </div>
                <div class="dn-spec">
                    <dt>Plan gratis</dt>
                    <dd>
                        @include('public.partials.mark', ['state' => $tool->has_free_plan ? 'yes' : 'no', 'text' => $tool->has_free_plan ? 'Sí' : 'No'])
                        @if($tool->free_plan_notes)<small>{{ $tool->free_plan_notes }}</small>@endif
                    </dd>
                </div>
                <div class="dn-spec">
                    <dt>Español</dt>
                    <dd>@include('public.partials.mark', ['state' => match ($tool->spanish_support) { \App\ToolSpanishSupport::Full => 'yes', \App\ToolSpanishSupport::Partial => 'partial', default => 'no' }, 'text' => $tool->spanish_support?->getLabel()])</dd>
                </div>
                <div class="dn-spec">
                    <dt>¿Pide tarjeta para probarla?</dt>
                    <dd>@include('public.partials.mark', ['state' => $tool->requires_card ? 'no' : 'yes', 'text' => $tool->requires_card ? 'Sí' : 'No'])</dd>
                </div>
                <div class="dn-spec">
                    <dt>Pagos desde Latinoamérica</dt>
                    <dd>
                        @if($tool->latam_payments === null)
                            <span class="dn-soft">Sin verificar</span>
                        @else
                            @include('public.partials.mark', ['state' => $tool->latam_payments ? 'yes' : 'no', 'text' => $tool->latam_payments ? 'Sí' : 'No'])
                        @endif
                    </dd>
                </div>
                <div class="dn-spec">
                    <dt>Plataformas</dt>
                    <dd>{{ $tool->platformList() !== [] ? implode(', ', array_map(fn ($platform) => $platform->getLabel(), $tool->platformList())) : '—' }}</dd>
                </div>
            </dl>

            @if($tool->best_for || $tool->not_for)
                <section class="dn-block" aria-labelledby="para-quien">
                    <h2 id="para-quien" class="dn-h2">¿Es para ti?</h2>
                    <div class="dn-split">
                        @if($tool->best_for)
                            <div class="dn-fit dn-fit--yes">
                                <h3 class="dn-h3">Te conviene si…</h3>
                                <p style="margin-top:.5rem">{{ $tool->best_for }}</p>
                            </div>
                        @endif
                        @if($tool->not_for)
                            <div class="dn-fit dn-fit--no">
                                <h3 class="dn-h3">Mejor busca otra si…</h3>
                                <p style="margin-top:.5rem">{{ $tool->not_for }}</p>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if($tool->image)
                <figure class="dn-block dn-figure">
                    <x-content-image :media="$tool->image" :alt="$tool->imageAlt() ?? ''" />
                </figure>
            @endif

            @if($tool->review)
                <section class="dn-block" aria-labelledby="prueba">
                    <h2 id="prueba" class="dn-h2">Nuestra prueba</h2>
                    <div class="dn-prose">{!! \App\Services\ContentHtml::sanitize($tool->review) !!}</div>
                </section>
            @endif

            @if($tool->prosList() !== [] || $tool->consList() !== [])
                <section class="dn-block" aria-labelledby="pros-contras">
                    <h2 id="pros-contras" class="dn-h2">Lo bueno y lo mejorable</h2>
                    <div class="dn-split">
                        @if($tool->prosList() !== [])
                            <div>
                                <h3 class="dn-h3">Lo bueno</h3>
                                <ul class="dn-list">
                                    @foreach($tool->prosList() as $pro)
                                        <li class="dn-mark--yes">@include('public.partials.icon', ['name' => 'check', 'size' => 16])<span>{{ $pro }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if($tool->consList() !== [])
                            <div>
                                <h3 class="dn-h3">Lo mejorable</h3>
                                <ul class="dn-list">
                                    @foreach($tool->consList() as $con)
                                        <li class="dn-mark--no">@include('public.partials.icon', ['name' => 'x', 'size' => 16])<span>{{ $con }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if($tool->privacy_notes)
                <section class="dn-block" aria-labelledby="privacidad">
                    <h2 id="privacidad" class="dn-h2">Privacidad y datos</h2>
                    <p class="dn-prose">{{ $tool->privacy_notes }}</p>
                </section>
            @endif

            @if($tool->reviewed_at)
                <p class="dn-block dn-soft dn-small">Última revisión: <time datetime="{{ $tool->reviewed_at->toDateString() }}">{{ $tool->reviewed_at->translatedFormat('j \d\e F \d\e Y') }}</time>.</p>
            @endif
        </article>

        @if($alternatives->isNotEmpty())
            <section class="dn-section" aria-labelledby="alternativas" style="padding-bottom:0">
                <div class="dn-section-head">
                    <h2 id="alternativas" class="dn-h2">Alternativas a {{ $tool->name }}</h2>
                    <a class="dn-link" href="{{ route('tools.alternatives', ['slug' => $tool->slug]) }}">Comparar todas @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
                </div>
                <div class="dn-panel">
                    @include('public.partials.results-table', ['tools' => $alternatives->take(3), 'caption' => 'Alternativas a '.$tool->name])
                </div>
            </section>
        @endif
    </div>
@endsection
