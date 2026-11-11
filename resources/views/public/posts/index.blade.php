@extends('public.layout')

@section('seo')
    @if($category)
        <x-seo :title="'Guías de '.$category->name" :description="'Guías y tutoriales de '.$category->name.' explicados en español, paso a paso y con herramientas probadas.'" />
    @else
        <x-seo title="Guías de IA y herramientas digitales" description="Tutoriales, comparativas y guías de IA y herramientas digitales, explicadas en español y con ejemplos reales." />
    @endif
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                @if($category)
                    <a href="{{ route('posts.index') }}">Guías</a> <span aria-hidden="true">/</span>
                    <span aria-current="page">{{ $category->name }}</span>
                @else
                    <span aria-current="page">Guías</span>
                @endif
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1.25rem">{{ $category ? 'Guías de '.$category->name : 'Guías' }}</h1>
            <p class="dn-lede">Tutoriales, comparativas y explicaciones para sacar partido a la IA y a las herramientas digitales, sin tecnicismos.</p>

            @if($categories->isNotEmpty())
                <nav aria-label="Temas" class="dn-chips" style="margin-top:1.75rem">
                    <a class="dn-chip" href="{{ route('posts.index') }}" @if(! $category) aria-current="page" @endif>Todas</a>
                    @foreach($categories as $item)
                        <a class="dn-chip" href="{{ route('posts.category', ['slug' => $item->slug]) }}" @if($category?->is($item)) aria-current="page" @endif>{{ $item->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    <div class="dn-wrap">
        @if($posts->isEmpty())
            <div class="dn-panel dn-empty">
                <p class="dn-h3">Todavía no hay guías aquí</p>
                <p class="dn-soft" style="margin-top:.4rem">Mientras tanto, <a class="dn-link" href="{{ route('workflows.index') }}">mira los flujos paso a paso</a>.</p>
            </div>
        @else
            @php($lead = $posts->onFirstPage() ? $posts->first() : null)
            @if($lead)
                <a class="dn-panel dn-feature {{ $lead->image ? 'dn-feature--media' : '' }}" href="{{ route('posts.show', ['slug' => $lead->slug]) }}">
                    @if($lead->image)
                        <div class="dn-feature-media">@include('public.partials.post-media', ['post' => $lead, 'priority' => true])</div>
                    @endif
                    <div class="dn-feature-body">
                        <p class="dn-byline">
                            @foreach($lead->categories->take(2) as $postCategory)
                                <span class="dn-pill dn-pill--sky">{{ $postCategory->name }}</span>
                            @endforeach
                            <span>{{ $lead->readingMinutes() }} min de lectura</span>
                        </p>
                        <h2 class="dn-feature-title">{{ $lead->title }}</h2>
                        @if($lead->excerpt)
                            <p class="dn-soft">{{ $lead->excerpt }}</p>
                        @endif
                        <span class="dn-link">Leer la guía @include('public.partials.icon', ['name' => 'arrow', 'size' => 16])</span>
                    </div>
                </a>
            @endif

            @php($rest = $lead ? $posts->getCollection()->slice(1) : $posts->getCollection())
            @if($rest->isNotEmpty())
                <div class="dn-panel" style="margin-top:1.25rem">
                    <ul class="dn-items">
                        @foreach($rest as $post)
                            @include('public.partials.post-item', ['post' => $post])
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($posts->hasPages())
                <div style="margin-top:1.5rem">{{ $posts->links() }}</div>
            @endif
        @endif
    </div>
@endsection
