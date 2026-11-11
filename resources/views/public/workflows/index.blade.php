@extends('public.layout')

@section('seo')
    <x-seo title="Flujos de trabajo con IA paso a paso" description="Casos reales paso a paso: qué herramientas usar, qué prompts copiar, cuánto tiempo lleva y cuánto cuesta." />
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                <span aria-current="page">Flujos</span>
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1rem">Flujos de trabajo</h1>
            <p class="dn-lede">Casos reales de principio a fin: qué herramientas usamos, en qué orden, qué prompts copiar y cuánto tiempo y dinero lleva.</p>

            <nav aria-label="Para quién" class="dn-chips dn-chips--scroll">
                <a class="dn-chip" href="{{ route('workflows.index', array_filter(['nivel' => $filters['nivel']?->value, 'tarea' => $filters['tarea']?->slug])) }}" @if(! $filters['para']) aria-current="page" @endif>Todos</a>
                @foreach(\App\WorkflowProfession::cases() as $profession)
                    <a class="dn-chip" href="{{ route('workflows.index', array_filter(['para' => $profession->value, 'nivel' => $filters['nivel']?->value, 'tarea' => $filters['tarea']?->slug])) }}" @if($filters['para'] === $profession) aria-current="page" @endif>{{ $profession->getLabel() }}</a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="dn-wrap">
        <div class="dn-panel">
            <form method="GET" class="dn-filters" aria-label="Filtrar flujos">
                @if($filters['para'])
                    <input type="hidden" name="para" value="{{ $filters['para']->value }}">
                @endif
                <label class="dn-field">
                    Tarea
                    <select class="dn-select" name="tarea">
                        <option value="">Todas</option>
                        @foreach($tasks as $task)
                            <option value="{{ $task->slug }}" @selected($filters['tarea']?->is($task))>{{ $task->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dn-field">
                    Dificultad
                    <select class="dn-select" name="nivel">
                        <option value="">Todas</option>
                        @foreach(\App\WorkflowDifficulty::cases() as $difficulty)
                            <option value="{{ $difficulty->value }}" @selected($filters['nivel'] === $difficulty)>{{ $difficulty->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="dn-btn dn-btn--sm" type="submit">Aplicar filtros</button>
                @if($filters['para'] || $filters['nivel'] || $filters['tarea'])
                    <a class="dn-link" href="{{ route('workflows.index') }}">Quitar filtros</a>
                @endif
                <span class="dn-count">{{ trans_choice(':count flujo|:count flujos', $workflows->total(), ['count' => $workflows->total()]) }}</span>
            </form>

            @if($workflows->isEmpty())
                <div class="dn-empty">
                    <p class="dn-h3">No hay flujos con estos filtros</p>
                    <p class="dn-soft" style="margin-top:.4rem"><a class="dn-link" href="{{ route('workflows.index') }}">Ver todos los flujos</a></p>
                </div>
            @else
                <ul class="dn-items">
                    @foreach($workflows as $workflow)
                        @include('public.partials.workflow-item', ['workflow' => $workflow])
                    @endforeach
                </ul>
            @endif
        </div>

        @if($workflows->hasPages())
            <div style="margin-top:1.5rem">{{ $workflows->links() }}</div>
        @endif
    </div>
@endsection
