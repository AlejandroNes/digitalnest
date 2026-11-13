@extends('nest.layout')

@section('seo')
    <x-seo />
@endsection

@section('content')
    <div class="mx-auto flex max-w-xl flex-col gap-6">
        <x-nest.page-header title="Contacto" subtitle="¿Una oferta caducada, una app que quieres que probemos o una pregunta? Escríbenos." />
        <div class="nest-card p-5 lg:p-7 [&_button[type=submit]]:mt-2 [&_button[type=submit]]:h-12 [&_button[type=submit]]:w-full [&_button[type=submit]]:rounded-full [&_button[type=submit]]:bg-accent [&_button[type=submit]]:font-semibold [&_button[type=submit]]:text-accent-ink [&_input]:h-12 [&_input]:w-full [&_input]:rounded-xl [&_input]:border [&_input]:border-line [&_input]:bg-raised [&_input]:px-4 [&_input[type=checkbox]]:h-auto [&_input[type=checkbox]]:w-auto [&_label]:text-sm [&_label]:font-medium [&_textarea]:w-full [&_textarea]:rounded-xl [&_textarea]:border [&_textarea]:border-line [&_textarea]:bg-raised [&_textarea]:p-4">
            <x-contact-form />
        </div>
    </div>
    <x-contact-assistant />
@endsection
