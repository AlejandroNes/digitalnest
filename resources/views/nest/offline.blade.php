@extends('nest.layout')

@section('seo')
    <title>Sin conexión · DigitalNest</title>
    <meta name="robots" content="noindex">
@endsection

@section('content')
    <x-nest.empty-state title="Estás sin conexión" icon="signal-slash" class="mx-auto mt-8 max-w-md">
        Revisa tu conexión y vuelve a intentarlo. Tus ofertas guardadas te esperan en Guardados.
    </x-nest.empty-state>
@endsection
