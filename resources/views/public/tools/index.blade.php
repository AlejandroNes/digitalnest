@extends('public.layout')

@section('seo')
    @if($category)
        <x-seo :content="$category" :title="$category->seo_title ?: 'Herramientas para '.mb_strtolower($category->name).' probadas'" />
    @else
        <x-seo title="Herramientas digitales y de IA probadas" description="Herramientas de IA y productividad probadas en español: precio real en USD, plan gratis, nota y alternativas." />
    @endif
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                @if($category)
                    <a href="{{ route('tools.index') }}">Herramientas</a> <span aria-hidden="true">/</span>
                    <span aria-current="page">{{ $category->name }}</span>
                @else
                    <span aria-current="page">Herramientas</span>
                @endif
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1.25rem">
                {{ $category ? 'Herramientas para '.mb_strtolower($category->name) : 'Herramientas probadas' }}
            </h1>
            <p class="dn-lede">{{ $category?->description ?: 'Cada herramienta la usamos en tareas reales. Compara su nota, precio, plan gratis y cómo funciona en español.' }}</p>

            @if($categories->isNotEmpty())
                <nav aria-label="Tareas" class="dn-chips" style="margin-top:1.75rem">
                    <a class="dn-chip" href="{{ route('tools.index') }}" @if(! $category) aria-current="page" @endif>Todas</a>
                    @foreach($categories as $item)
                        <a class="dn-chip" href="{{ route('tools.category', ['slug' => $item->slug]) }}" @if($category?->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    <div class="dn-wrap">
        <div class="dn-panel">
            <form method="GET" class="dn-filters" aria-label="Filtrar herramientas">
                <label class="dn-field">
                    Precio
                    <select class="dn-select" name="precio">
                        <option value="">Todos</option>
                        @foreach(\App\ToolPricingModel::cases() as $pricing)
                            <option value="{{ $pricing->value }}" @selected($filters['precio'] === $pricing->value)>{{ $pricing->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dn-check"><input type="checkbox" name="gratis" value="1" @checked($filters['gratis'])> Con plan gratis</label>
                <label class="dn-check"><input type="checkbox" name="espanol" value="1" @checked($filters['espanol'])> Funciona bien en español</label>
                <button class="dn-btn dn-btn--sm" type="submit">Aplicar filtros</button>
                @if($filters['precio'] || $filters['gratis'] || $filters['espanol'])
                    <a class="dn-link" href="{{ url()->current() }}">Quitar filtros</a>
                @endif
                <span class="dn-soft dn-small" style="margin-left:auto; align-self:center">
                    {{ trans_choice(':count herramienta|:count herramientas', $tools->total(), ['count' => $tools->total()]) }}
                </span>
            </form>

            @if($tools->isEmpty())
                <div class="dn-empty">
                    <p class="dn-h3">Ninguna herramienta cumple estos filtros</p>
                    <p class="dn-soft" style="margin-top:.4rem">Quita alguno o <a class="dn-link" href="{{ route('tools.index') }}">mira todas las herramientas</a>.</p>
                </div>
            @else
                @include('public.partials.results-table', ['tools' => $tools, 'caption' => $category ? 'Herramientas de '.$category->name : 'Herramientas probadas'])
            @endif
        </div>

        @if($tools->hasPages())
            <div style="margin-top:1.5rem">{{ $tools->links() }}</div>
        @endif
    </div>
@endsection
