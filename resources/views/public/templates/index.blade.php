@extends('public.layout')

@section('seo')
    <x-seo title="Plantillas de IA listas para copiar" description="Prompts, instrucciones de sistema, skills y automatizaciones probadas, listas para copiar o descargar." />
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                <span aria-current="page">Plantillas</span>
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1rem">Plantillas listas para usar</h1>
            <p class="dn-lede">Prompts, instrucciones de sistema, skills y automatizaciones que usamos en nuestros flujos. Cópialas o descárgalas y adáptalas a tu caso.</p>

            <nav aria-label="Tipo de plantilla" class="dn-chips dn-chips--scroll">
                <a class="dn-chip" href="{{ route('templates.index', array_filter(['herramienta' => $filters['herramienta']?->slug])) }}" @if(! $filters['tipo']) aria-current="page" @endif>Todas</a>
                @foreach(\App\TemplateType::cases() as $type)
                    <a class="dn-chip" href="{{ route('templates.index', array_filter(['tipo' => $type->value, 'herramienta' => $filters['herramienta']?->slug])) }}" @if($filters['tipo'] === $type) aria-current="page" @endif>{{ $type->getLabel() }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="dn-wrap">
        <div class="dn-panel">
            <form method="GET" class="dn-filters" aria-label="Filtrar plantillas">
                @if($filters['tipo'])
                    <input type="hidden" name="tipo" value="{{ $filters['tipo']->value }}">
                @endif
                <label class="dn-field">
                    Funciona con
                    <select class="dn-select" name="herramienta">
                        <option value="">Cualquier herramienta</option>
                        @foreach($toolOptions as $tool)
                            <option value="{{ $tool->slug }}" @selected($filters['herramienta']?->is($tool))>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="dn-btn dn-btn--sm" type="submit">Aplicar filtros</button>
                @if($filters['tipo'] || $filters['herramienta'])
                    <a class="dn-link" href="{{ route('templates.index') }}">Quitar filtros</a>
                @endif
                <span class="dn-count">{{ trans_choice(':count plantilla|:count plantillas', $templates->total(), ['count' => $templates->total()]) }}</span>
            </form>

            @if($templates->isEmpty())
                <div class="dn-empty">
                    <p class="dn-h3">No hay plantillas con estos filtros</p>
                    <p class="dn-soft" style="margin-top:.4rem"><a class="dn-link" href="{{ route('templates.index') }}">Ver todas las plantillas</a></p>
                </div>
            @else
                <ul class="dn-items">
                    @foreach($templates as $template)
                        @include('public.partials.template-item', ['template' => $template])
                    @endforeach
                </ul>
            @endif
        </div>

        @if($templates->hasPages())
            <div style="margin-top:1.5rem">{{ $templates->links() }}</div>
        @endif
    </div>
@endsection
