@extends('public.tools.layout')

@section('seo')
    <x-seo :title="'Alternativas a '.$tool->name" :description="'Las mejores alternativas a '.$tool->name.' probadas y comparadas: precio, plan gratis y funcionamiento en español.'" />
@endsection

@section('content')
    <nav aria-label="Migas de pan">
        <a href="{{ route('tools.index') }}">Herramientas</a>
        › <a href="{{ route('tools.show', ['slug' => $tool->slug]) }}">{{ $tool->name }}</a>
        › <span aria-current="page">Alternativas</span>
    </nav>

    <h1>Alternativas a {{ $tool->name }}</h1>
    <p>{{ $tool->tagline }}</p>

    @foreach($alternatives as $alternative)
        @include('public.tools.card', ['tool' => $alternative])
    @endforeach
@endsection
