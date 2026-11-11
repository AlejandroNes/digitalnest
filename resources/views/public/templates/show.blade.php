@extends('public.layout')

@section('seo')
    <x-seo :content="$template" :description="$template->seo_description ?: $template->summary" />
@endsection

@section('content')
    <div class="dn-wrap">
        <nav class="dn-crumbs" aria-label="Migas de pan" style="padding-bottom:1.25rem">
            <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
            <a href="{{ route('templates.index') }}">Plantillas</a> <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $template->title }}</span>
        </nav>

        <div class="dn-with-aside">
            <article class="dn-panel">
                <header class="dn-report-head" style="grid-template-columns:1fr">
                    <div>
                        <div class="dn-report-meta">
                            <a class="dn-pill dn-pill--sky" style="text-decoration:none" href="{{ route('templates.index', ['tipo' => $template->type->value]) }}">{{ $template->type->getLabel() }}</a>
                            @if($template->tested_at)
                                <span class="dn-soft dn-small">Probada el <time datetime="{{ $template->tested_at->toDateString() }}">{{ $template->tested_at->translatedFormat('j \d\e F \d\e Y') }}</time></span>
                            @endif
                            @if($template->is_featured)
                                <span class="dn-tag">Recomendada</span>
                            @endif
                        </div>
                        <h1 class="dn-display dn-title" style="margin-top:1rem">{{ $template->title }}</h1>
                        <p class="dn-lede">{{ $template->summary }}</p>
                    </div>
                </header>

                <section class="dn-block" aria-labelledby="plantilla">
                    <h2 id="plantilla" class="dn-h2">La plantilla</h2>
                    <div class="dn-code">
                        <div class="dn-code-bar">
                            <span class="dn-code-label">{{ $template->format->getLabel() }} · {{ $template->downloadFilename() }}</span>
                            <div class="dn-code-actions">
                                <button type="button" class="dn-btn dn-btn--code" data-copy="contenido-plantilla">
                                    @include('public.partials.icon', ['name' => 'copy', 'size' => 16])
                                    <span data-copy-status aria-live="polite">Copiar</span>
                                </button>
                                <a class="dn-btn dn-btn--code-ghost" href="{{ route('templates.download', ['slug' => $template->slug]) }}" download>
                                    @include('public.partials.icon', ['name' => 'download', 'size' => 16]) Descargar
                                </a>
                            </div>
                        </div>
                        <pre id="contenido-plantilla" tabindex="0" aria-label="Contenido de la plantilla">{{ $template->content }}</pre>
                    </div>
                    @if($template->variableList() !== [])
                        <p class="dn-soft dn-small" style="margin-top:1rem; font-weight:700">Sustituye estos huecos antes de usarla:</p>
                        <p class="dn-vars">
                            @foreach($template->variableList() as $variable)
                                <span class="dn-var">{{ $variable }}</span>
                            @endforeach
                        </p>
                    @endif
                </section>

                @if($template->usage)
                    <section class="dn-block" aria-labelledby="uso">
                        <h2 id="uso" class="dn-h2">Cómo usarla</h2>
                        <div class="dn-prose">{!! \App\Services\ContentHtml::sanitize($template->usage) !!}</div>
                    </section>
                @endif

                @if($template->example_output)
                    <section class="dn-block" aria-labelledby="ejemplo">
                        <h2 id="ejemplo" class="dn-h2">Ejemplo de resultado</h2>
                        <p class="dn-example">{{ $template->example_output }}</p>
                    </section>
                @endif
            </article>

            <aside class="dn-aside" aria-label="Relacionado">
                @if($tools->isNotEmpty())
                    <div class="dn-panel">
                        <div class="dn-panel-head"><h2 class="dn-h3">Funciona con</h2></div>
                        <ul class="dn-rank">
                            @foreach($tools as $tool)
                                <li>
                                    @include('public.partials.logo', ['tool' => $tool])
                                    <div class="dn-rank-body">
                                        <a class="dn-tool-name" href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
                                        <p class="dn-rank-meta"><span>{{ $tool->category?->name }}</span></p>
                                    </div>
                                    @include('public.partials.score', ['tool' => $tool])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if($workflows->isNotEmpty())
                    <div class="dn-panel" style="margin-top:1rem">
                        <div class="dn-panel-head"><h2 class="dn-h3">Se usa en estos flujos</h2></div>
                        <ul class="dn-items">
                            @foreach($workflows as $workflow)
                                <li class="dn-item">
                                    <a class="dn-item-title" style="font-size:1rem" href="{{ route('workflows.show', ['slug' => $workflow->slug]) }}">{{ $workflow->title }}</a>
                                    <p class="dn-meta"><span class="dn-pill dn-pill--sky">{{ $workflow->durationLabel() }}</span></p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
