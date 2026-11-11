{{ $identity->company_name }}

Nueva consulta de contacto

Nombre: {{ $contact->name }}
Correo: {{ $contact->email }}
@if($contact->phone)Teléfono: {{ $contact->phone }}
@endif
@if($contact->subject)Asunto: {{ $contact->subject }}
@endif
Mensaje:
{{ $contact->message }}

Consulta guardada en la bandeja administrativa.
