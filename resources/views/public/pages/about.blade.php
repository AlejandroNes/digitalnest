@extends('public.pages.page', ['title' => 'Sobre DigitalNest', 'lede' => 'Herramientas digitales y de IA probadas, explicadas en español, para quien trabaja por su cuenta o en un equipo pequeño.'])

@section('seo')
    <x-seo title="Sobre DigitalNest" description="Qué es DigitalNest, para quién escribimos y cómo trabajamos." />
@endsection

@section('body')
    <h2>Qué hacemos</h2>
    <p>Probamos herramientas digitales y de IA en tareas reales y te contamos lo que necesitas para decidir: cuánto cuestan, si el plan gratis sirve, cómo funcionan en español y qué alternativas tienes.</p>

    <h2>Para quién</h2>
    <p>Escribimos para freelancers, creadores y pequeños negocios de habla hispana que quieren trabajar mejor con herramientas digitales sin perder horas investigando.</p>

    <h2>Qué encontrarás</h2>
    <ul>
        <li><a href="{{ route('tools.index') }}">Herramientas</a>: fichas probadas con nota, precio y alternativas.</li>
        <li><a href="{{ route('workflows.index') }}">Flujos de trabajo</a>: casos reales paso a paso.</li>
        <li><a href="{{ route('templates.index') }}">Plantillas</a>: prompts y automatizaciones listas para copiar.</li>
        <li><a href="{{ route('posts.index') }}">Guías</a>: tutoriales, comparativas y explicaciones.</li>
    </ul>

    <h2>Cómo trabajamos</h2>
    <p>Cada publicación pasa por nuestro <a href="{{ route('pages.method') }}">método de prueba</a>. Algunos enlaces son de afiliado y siempre los señalamos; <a href="{{ route('pages.affiliates') }}">así funcionan</a>.</p>

    <p>¿Tienes una sugerencia o encontraste un error? <a href="{{ route('contact.create') }}">Escríbenos</a>.</p>
@endsection
