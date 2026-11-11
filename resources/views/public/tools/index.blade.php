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
            <h1 class="dn-display dn-title" style="margin-top:1rem">
                {{ $category ? 'Herramientas para '.mb_strtolower($category->name) : 'Herramientas probadas' }}
            </h1>
            <p class="dn-lede">{{ $category?->description ?: 'Cada herramienta la usamos en tareas reales. Compara su nota, precio, plan gratis y cómo funciona en español.' }}</p>

            @if($categories->isNotEmpty())
                <nav aria-label="Tareas" class="dn-chips dn-chips--scroll">
                    <a class="dn-chip" href="{{ route('tools.index') }}" @if(! $category) aria-current="page" @endif>Todas</a>
                    @foreach($categories as $item)
                        <a class="dn-chip" href="{{ route('tools.category', ['slug' => $item->slug]) }}" @if($category?->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    <div class="dn-wrap">
        <div class="dn-toolbar">
            <form method="GET" aria-label="Filtrar herramientas">
                <label class="dn-field">
                    <span class="dn-visually-hidden">Precio</span>
                    <select class="dn-select" name="precio" data-autosubmit>
                        <option value="">Cualquier precio</option>
                        @foreach(\App\ToolPricingModel::cases() as $pricing)
                            <option value="{{ $pricing->value }}" @selected($filters['precio'] === $pricing->value)>{{ $pricing->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dn-check"><input type="checkbox" name="gratis" value="1" @checked($filters['gratis']) data-autosubmit> Con plan gratis</label>
                <label class="dn-check"><input type="checkbox" name="espanol" value="1" @checked($filters['espanol']) data-autosubmit> Bien en español</label>
                <noscript><button class="dn-btn dn-btn--sm" type="submit">Aplicar</button></noscript>
                @if($filters['precio'] || $filters['gratis'] || $filters['espanol'])
                    <a class="dn-link" href="{{ url()->current() }}">Quitar filtros</a>
                @endif
            </form>
            <span class="dn-soft dn-small">{{ trans_choice(':count herramienta|:count herramientas', $tools->total(), ['count' => $tools->total()]) }}</span>
        </div>

        @if($tools->isEmpty())
            <div class="dn-panel dn-empty">
                <p class="dn-h3">Ninguna herramienta cumple estos filtros</p>
                <p class="dn-soft" style="margin-top:.4rem">Quita alguno o <a class="dn-link" href="{{ route('tools.index') }}">mira todas las herramientas</a>.</p>
            </div>
        @else
            @include('public.partials.tool-cards', ['tools' => $tools])
        @endif

        @if($tools->hasPages())
            <div style="margin-top:1.5rem">{{ $tools->links() }}</div>
        @endif
    </div>
@endsection
