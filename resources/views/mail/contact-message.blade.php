@extends('mail.layout')
@section('content')
    <h1 style="font-size:22px;margin:0 0 20px;">Nueva consulta de contacto</h1>
    <p><strong>Nombre:</strong> {{ $contact->name }}</p>
    <p><strong>Correo:</strong> {{ $contact->email }}</p>
    @if($contact->phone)<p><strong>Teléfono:</strong> {{ $contact->phone }}</p>@endif
    @if($contact->subject)<p><strong>Asunto:</strong> {{ $contact->subject }}</p>@endif
    <p><strong>Mensaje:</strong></p>
    <p style="white-space:pre-wrap;overflow-wrap:anywhere;">{!! nl2br(e($contact->message)) !!}</p>
    <p style="font-size:14px;color:#4b5563;">Consulta guardada en la bandeja administrativa.</p>
@endsection
