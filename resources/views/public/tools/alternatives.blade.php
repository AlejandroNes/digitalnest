@extends('public.layout')

@section('seo')
    <x-seo :title="'Alternativas a '.$tool->name.' probadas'" :description="'Comparamos '.$tool->name.' con '.$alternatives->count().' alternativas probadas: precio en USD, plan gratis, nota y funcionamiento en español.'" />
@endsection

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                <a href="{{ route('tools.index') }}">Herramientas</a> <span aria-hidden="true">/</span>
                <a href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a> <span aria-hidden="true">/</span>
                <span aria-current="page">Alternativas</span>
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1rem">Alternativas a {{ $tool->name }}</h1>
            <p class="dn-lede">¿{{ $tool->name }} no encaja contigo? Compárala con otras opciones que también probamos.</p>
        </div>
    </div>

    <div class="dn-wrap">
        <div class="dn-panel">
            @include('public.partials.results-table', [
                'tools' => collect([$tool])->concat($alternatives),
                'caption' => 'Comparativa de '.$tool->name.' y sus alternativas',
            ])
        </div>
    </div>
@endsection
