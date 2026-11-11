@extends('mail.layout')
@section('content')
    <h1 style="font-size:22px;margin:0 0 20px;">Prueba de configuración SMTP</h1>
    <p>Este es un correo de prueba enviado desde el panel administrativo de {{ $identity->company_name }}.</p>
    <p>Si puedes leerlo, este mensaje llegó a tu buzón.</p>
@endsection
