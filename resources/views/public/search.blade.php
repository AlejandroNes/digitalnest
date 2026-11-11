@extends('public.layout')

@section('seo')
    <x-seo :title="$term !== '' ? 'Resultados para «'.$term.'»' : 'Buscar'" description="Busca herramientas, flujos de trabajo, plantillas y guías en DigitalNest." :noindex="true" />
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <h1 class="dn-display dn-title" style="margin-top:1rem">
                @if($term !== '')
                    Resultados para «{{ $term }}»
                @else
                    Buscar
                @endif
            </h1>
            @include('public.partials.search-form', ['value' => $term, 'id' => 'buscar-resultados', 'class' => 'dn-search--page'])
            @if($term !== '' && mb_strlen($term) < 2)
                <p class="dn-soft" style="margin-top:1rem">Escribe al menos dos letras.</p>
            @elseif($term !== '')
                <p class="dn-soft" style="margin-top:1rem" role="status">{{ trans_choice(':count resultado|:count resultados', $total, ['count' => $total]) }}</p>
            @endif
        </div>
    </div>

    <div class="dn-wrap">
        @if($term !== '' && $total === 0 && mb_strlen($term) >= 2)
            <div class="dn-panel dn-empty">
                <p class="dn-h3">No encontramos nada para «{{ $term }}»</p>
                <p class="dn-soft" style="margin-top:.5rem">Prueba con otra palabra o explora <a class="dn-link" href="{{ route('tools.index') }}">todas las herramientas</a>.</p>
                <p class="dn-soft" style="margin-top:.5rem">¿Quieres que probemos una herramienta? <a class="dn-link" href="{{ route('contact.create') }}">Sugiérela</a>.</p>
            </div>
        @endif

        @if(($results['tools'] ?? collect())->isNotEmpty())
            <section aria-labelledby="r-herramientas" style="margin-bottom:2.5rem">
                <h2 id="r-herramientas" class="dn-h2" style="margin-bottom:1rem">Herramientas</h2>
                @include('public.partials.tool-cards', ['tools' => $results['tools']])
            </section>
        @endif

        @if(($results['workflows'] ?? collect())->isNotEmpty())
            <section aria-labelledby="r-flujos" style="margin-bottom:2.5rem">
                <h2 id="r-flujos" class="dn-h2" style="margin-bottom:1rem">Flujos de trabajo</h2>
                <div class="dn-panel"><ul class="dn-items">
                    @foreach($results['workflows'] as $workflow)
                        @include('public.partials.workflow-item', ['workflow' => $workflow])
                    @endforeach
                </ul></div>
            </section>
        @endif

        @if(($results['templates'] ?? collect())->isNotEmpty())
            <section aria-labelledby="r-plantillas" style="margin-bottom:2.5rem">
                <h2 id="r-plantillas" class="dn-h2" style="margin-bottom:1rem">Plantillas</h2>
                <div class="dn-panel"><ul class="dn-items">
                    @foreach($results['templates'] as $template)
                        @include('public.partials.template-item', ['template' => $template])
                    @endforeach
                </ul></div>
            </section>
        @endif

        @if(($results['posts'] ?? collect())->isNotEmpty())
            <section aria-labelledby="r-guias">
                <h2 id="r-guias" class="dn-h2" style="margin-bottom:1rem">Guías</h2>
                <div class="dn-panel"><ul class="dn-items">
                    @foreach($results['posts'] as $post)
                        @include('public.partials.post-item', ['post' => $post])
                    @endforeach
                </ul></div>
            </section>
        @endif
    </div>
@endsection
