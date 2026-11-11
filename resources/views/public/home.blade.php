@extends('public.layout')

@section('seo')
    <x-seo />
@endsection

@section('content')
    <section class="dn-hero" aria-labelledby="titular">
        <div class="dn-wrap dn-hero-grid">
            <div class="dn-rise">
                <h1 id="titular" class="dn-display dn-hero-title">Encuentra la herramienta que de verdad <mark>te sirve</mark></h1>
                <p class="dn-lede">Probamos herramientas digitales y de IA en tareas reales y te contamos lo que importa: precio en USD, si el plan gratis sirve y cómo funcionan en español.</p>

                <div class="dn-hero-actions">
                    <a class="dn-btn dn-btn--lg" href="{{ route('tools.index') }}">Explorar herramientas @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
                    <a class="dn-btn dn-btn--lg dn-btn--glass" href="#metodo">Cómo probamos</a>
                </div>

                @if($categories->isNotEmpty())
                    <div class="dn-hero-tasks">
                        <p>¿Qué quieres hacer?</p>
                        <nav aria-label="Tareas" class="dn-chips">
                            @foreach($categories as $category)
                                <a class="dn-chip" href="{{ route('tools.category', ['slug' => $category->slug]) }}">
                                    {{ $category->name }} <span class="dn-chip-count">{{ $category->tools_count }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endif
            </div>

            @if($featured->isNotEmpty())
                <div class="dn-panel dn-rise dn-rise--2">
                    <div class="dn-panel-head">
                        <h2 class="dn-h3">Nuestras recomendadas</h2>
                        <span class="dn-soft dn-small">Nota sobre 10</span>
                    </div>
                    <ol class="dn-rank">
                        @foreach($featured as $tool)
                            <li>
                                @include('public.partials.logo', ['tool' => $tool])
                                <div class="dn-rank-body">
                                    <a class="dn-tool-name" href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
                                    <p class="dn-rank-meta">
                                        <span>{{ $tool->category?->name }}</span>
                                        <span>{{ $tool->startingPriceLabel() ? 'desde '.$tool->startingPriceLabel().'/mes' : $tool->pricing_model?->getLabel() }}</span>
                                        @if($tool->has_free_plan)<span>Plan gratis</span>@endif
                                    </p>
                                </div>
                                @include('public.partials.score', ['tool' => $tool])
                            </li>
                        @endforeach
                    </ol>
                    <div class="dn-panel-foot">
                        <a class="dn-link" href="{{ route('tools.index') }}">Ver las {{ $toolCount }} herramientas @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="dn-wrap dn-section" aria-labelledby="metodo">
        <div class="dn-section-head">
            <div>
                <h2 id="metodo" class="dn-h2">Cómo probamos cada herramienta</h2>
                <p class="dn-soft" style="margin-top:.4rem">Ninguna ficha se publica sin pasar por estas tres comprobaciones.</p>
            </div>
        </div>
        <div class="dn-method">
            <div>
                <span class="dn-method-icon">@include('public.partials.icon', ['name' => 'briefcase', 'size' => 22])</span>
                <h3 class="dn-h3">La usamos en un trabajo real</h3>
                <p class="dn-soft" style="margin-top:.5rem; max-width:40ch">No copiamos la web del fabricante: cada herramienta la ponemos a prueba en tareas reales de freelance y pequeño negocio, de principio a fin.</p>
            </div>
            <div>
                <span class="dn-method-icon">@include('public.partials.icon', ['name' => 'ruler', 'size' => 22])</span>
                <h3 class="dn-h3">Medimos lo que te importa</h3>
                <p class="dn-soft" style="margin-top:.4rem">Precio real en USD, si el plan gratis sirve, si pide tarjeta y cómo responde en español.</p>
            </div>
            <div>
                <span class="dn-method-icon dn-method-icon--coral">@include('public.partials.icon', ['name' => 'refresh', 'size' => 22])</span>
                <h3 class="dn-h3">La revisamos cuando cambia</h3>
                <p class="dn-soft" style="margin-top:.4rem">Cada ficha muestra su fecha de prueba y se actualiza cuando cambian precios o funciones.</p>
            </div>
        </div>
    </section>

    @if($workflows->isNotEmpty())
        <section class="dn-wrap" aria-labelledby="flujos" style="padding-bottom:clamp(3rem, 2rem + 3vw, 5rem)">
            <div class="dn-section-head">
                <div>
                    <h2 id="flujos" class="dn-h2">Flujos paso a paso</h2>
                    <p class="dn-soft" style="margin-top:.4rem">Casos reales con las herramientas, los prompts, el tiempo y el coste.</p>
                </div>
                <a class="dn-link" href="{{ route('workflows.index') }}">Ver todos @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
            </div>
            <div class="dn-panel"><ul class="dn-items">
                @foreach($workflows as $workflow)
                    @include('public.partials.workflow-item', ['workflow' => $workflow])
                @endforeach
            </ul></div>
        </section>
    @endif

    @if($guides->isNotEmpty())
        <section class="dn-wrap" aria-labelledby="guias" style="padding-bottom:clamp(3rem, 2rem + 3vw, 5rem)">
            <div class="dn-section-head">
                <h2 id="guias" class="dn-h2">Guías recientes</h2>
                <a class="dn-link" href="{{ route('posts.index') }}">Todas las guías @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
            </div>
            <div class="dn-panel"><ul class="dn-items">
                @foreach($guides as $post)
                    @include('public.partials.post-item', ['post' => $post])
                @endforeach
            </ul></div>
        </section>
    @endif

    @if($latest->isNotEmpty())
        <section class="dn-wrap" aria-labelledby="recientes">
            <div class="dn-section-head">
                <h2 id="recientes" class="dn-h2">Recién probadas</h2>
                <a class="dn-link" href="{{ route('tools.index') }}">Ver todas @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
            </div>
            <div class="dn-panel">
                @include('public.partials.results-table', ['tools' => $latest, 'caption' => 'Herramientas probadas recientemente'])
            </div>
        </section>
    @endif
@endsection
