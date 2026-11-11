@extends('errors.layout')
@section('code', '503')
@section('title', 'Volvemos enseguida')
@section('description', 'Estamos haciendo mejoras en DigitalNest. Vuelve a intentarlo en unos minutos.')
@section('actions')
    <a class="btn" href="{{ url()->current() }}">Reintentar</a>
@endsection
