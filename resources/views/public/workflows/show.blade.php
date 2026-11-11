@extends('public.layout')

@section('seo')
    <x-seo :content="$workflow" :description="$workflow->seo_description ?: $workflow->summary" />
@endsection

@push('structured-data')
    <script type="application/ld+json">{!! json_encode($workflow->structuredData(route('workflows.show', ['slug' => $workflow->slug])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <div class="dn-wrap">
        <nav class="dn-crumbs" aria-label="Migas de pan" style="padding-bottom:1.25rem">
            <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
            <a href="{{ route('workflows.index') }}">Flujos</a> <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $workflow->title }}</span>
        </nav>

        <div class="dn-with-aside">
            <article class="dn-panel">
                <header class="dn-report-head" style="grid-template-columns:1fr">
                    <div>
                        <div class="dn-report-meta">
                            @if($workflow->category)
                                <a class="dn-link dn-small" href="{{ route('workflows.index', ['tarea' => $workflow->category->slug]) }}">{{ $workflow->category->name }}</a>
                            @endif
                            @if($workflow->tested_at)
                                <span class="dn-soft dn-small">Probado el <time datetime="{{ $workflow->tested_at->toDateString() }}">{{ $workflow->tested_at->translatedFormat('j \d\e F \d\e Y') }}</time></span>
                            @endif
                            @if($workflow->is_featured)
                                <span class="dn-tag">Recomendado</span>
                            @endif
                        </div>
                        <h1 class="dn-display dn-title" style="margin-top:1rem">{{ $workflow->title }}</h1>
                        <p class="dn-lede">{{ $workflow->summary }}</p>
                        @if($workflow->professionList() !== [])
                            <p class="dn-meta" style="margin-top:1.25rem">
                                <span class="dn-soft dn-small" style="font-weight:700">Para</span>
                                @foreach($workflow->professionList() as $profession)
                                    <a class="dn-pill dn-pill--sky" style="text-decoration:none" href="{{ route('workflows.index', ['para' => $profession->value]) }}">{{ $profession->getLabel() }}</a>
                                @endforeach
                            </p>
                        @endif
                    </div>
                </header>

                <dl class="dn-facts" aria-label="Datos del flujo">
                    <div><dt>Tiempo</dt><dd>{{ $workflow->durationLabel() }}</dd></div>
                    <div><dt>Coste</dt><dd>{{ $workflow->costLabel() }}</dd></div>
                    <div><dt>Dificultad</dt><dd>{{ $workflow->difficulty?->getLabel() }}</dd></div>
                    <div><dt>Pasos</dt><dd>{{ $workflow->steps->count() }}</dd></div>
                </dl>

                <section class="dn-block" aria-labelledby="objetivo">
                    <h2 id="objetivo" class="dn-h2">Qué vas a conseguir</h2>
                    <p class="dn-goal">{{ $workflow->goal }}</p>
                    @if($workflow->intro)
                        <div class="dn-prose" style="margin-top:1.5rem">{!! \App\Services\ContentHtml::sanitize($workflow->intro) !!}</div>
                    @endif
                </section>

                @if($workflow->image)
                    <figure class="dn-block dn-figure">
                        <x-content-image :media="$workflow->image" :alt="$workflow->imageAlt() ?? ''" />
                    </figure>
                @endif

                <section class="dn-block" aria-labelledby="pasos">
                    <h2 id="pasos" class="dn-h2">Paso a paso</h2>
                    <ol class="dn-steps">
                        @foreach($workflow->steps as $step)
                            <li class="dn-step" id="paso-{{ $loop->iteration }}">
                                <span class="dn-step-marker" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div>
                                    <h3 class="dn-h3" style="font-size:1.2rem">{{ $step->title }}</h3>
                                    @if($step->body)
                                        <div class="dn-prose" style="margin-top:.5rem">{!! \App\Services\ContentHtml::sanitize($step->body) !!}</div>
                                    @endif
                                    @if($step->image)
                                        <figure><x-content-image :media="$step->image" :alt="$step->imageAlt() ?? ''" /></figure>
                                    @endif
                                    @php($stepTool = $step->tool && $tools->contains($step->tool) ? $step->tool : null)
                                    @php($stepTemplate = $step->template && $templates->contains($step->template) ? $step->template : null)
                                    @if($stepTool || $stepTemplate)
                                        <div class="dn-step-uses">
                                            @if($stepTool)
                                                <a class="dn-use" href="{{ route('tools.show', ['slug' => $stepTool->slug]) }}">
                                                    @include('public.partials.logo', ['tool' => $stepTool])
                                                    {{ $stepTool->name }}
                                                </a>
                                            @endif
                                            @if($stepTemplate)
                                                <a class="dn-use" href="{{ route('templates.show', ['slug' => $stepTemplate->slug]) }}">
                                                    <span class="dn-use-icon">@include('public.partials.icon', ['name' => 'copy', 'size' => 16])</span>
                                                    {{ $stepTemplate->title }}
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if($workflow->result)
                    <section class="dn-block" aria-labelledby="resultado">
                        <h2 id="resultado" class="dn-h2">El resultado</h2>
                        <div class="dn-prose">{!! \App\Services\ContentHtml::sanitize($workflow->result) !!}</div>
                    </section>
                @endif
            </article>

            <aside class="dn-aside" aria-label="Lo que necesitas">
                @if($tools->isNotEmpty())
                    <div class="dn-panel">
                        <div class="dn-panel-head"><h2 class="dn-h3">Herramientas</h2></div>
                        <ul class="dn-rank">
                            @foreach($tools as $tool)
                                <li>
                                    @include('public.partials.logo', ['tool' => $tool])
                                    <div class="dn-rank-body">
                                        <a class="dn-tool-name" href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
                                        <p class="dn-rank-meta"><span>{{ $tool->startingPriceLabel() ? 'desde '.$tool->startingPriceLabel().'/mes' : $tool->pricing_model?->getLabel() }}</span></p>
                                    </div>
                                    @include('public.partials.score', ['tool' => $tool])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($templates->isNotEmpty())
                    <div class="dn-panel" style="margin-top:1rem">
                        <div class="dn-panel-head"><h2 class="dn-h3">Plantillas para copiar</h2></div>
                        <ul class="dn-rank">
                            @foreach($templates as $template)
                                <li>
                                    <span class="dn-use-icon" aria-hidden="true">@include('public.partials.icon', ['name' => 'copy', 'size' => 16])</span>
                                    <div class="dn-rank-body">
                                        <a class="dn-tool-name" href="{{ route('templates.show', ['slug' => $template->slug]) }}">{{ $template->title }}</a>
                                        <p class="dn-rank-meta"><span>{{ $template->type?->getLabel() }}</span></p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
