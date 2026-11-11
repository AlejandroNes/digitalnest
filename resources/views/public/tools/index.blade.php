@extends('public.tools.layout')

@section('seo')
    @if($category)
        <x-seo :content="$category" :title="$category->seo_title ?: 'Herramientas de '.$category->name" />
    @else
        <x-seo title="Herramientas digitales y de IA probadas" description="Herramientas de IA y productividad probadas y explicadas en español: precio real, plan gratis y alternativas." />
    @endif
@endsection

@section('content')
    <nav aria-label="Migas de pan">
        <a href="{{ route('tools.index') }}">Herramientas</a>
        @if($category)
            › <span aria-current="page">{{ $category->name }}</span>
        @endif
    </nav>

    <h1>{{ $category ? 'Herramientas de '.$category->name : 'Herramientas probadas' }}</h1>
    @if($category?->description)
        <p>{{ $category->description }}</p>
    @endif

    @if($categories->isNotEmpty())
        <nav aria-label="Categorías">
            <ul>
                @foreach($categories as $item)
                    <li>
                        <a href="{{ route('tools.category', ['slug' => $item->slug]) }}" @if($category?->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <form method="GET" aria-label="Filtrar herramientas">
        <label>
            Precio
            <select name="precio">
                <option value="">Todos</option>
                @foreach(\App\ToolPricingModel::cases() as $pricing)
                    <option value="{{ $pricing->value }}" @selected($filters['precio'] === $pricing->value)>{{ $pricing->getLabel() }}</option>
                @endforeach
            </select>
        </label>
        <label><input type="checkbox" name="gratis" value="1" @checked($filters['gratis'])> Con plan gratis</label>
        <label><input type="checkbox" name="espanol" value="1" @checked($filters['espanol'])> Funciona bien en español</label>
        <button type="submit">Filtrar</button>
    </form>

    @forelse($tools as $tool)
        @include('public.tools.card', ['tool' => $tool])
    @empty
        <p>No hay herramientas que coincidan con estos filtros.</p>
    @endforelse

    {{ $tools->links() }}
@endsection
