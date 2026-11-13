@extends('public.pages.page', ['title' => 'Sobre DigitalNest', 'lede' => 'Cupones, ofertas y rankings de las mejores apps, probadas y explicadas en español.'])

@section('seo')
    <x-seo title="Sobre DigitalNest" description="Qué es DigitalNest, para quién escribimos y cómo trabajamos." />
@endsection

@section('body')
    <h2>Qué hacemos</h2>
    <p>Reunimos cupones y ofertas verificados de apps de todos los sectores (IA, vídeo, audio, imágenes, hosting, VPN y más) y las comparamos en rankings honestos para que pagues menos por las mejores herramientas.</p>

    <h2>Para quién</h2>
    <p>Escribimos para freelancers, creadores y pequeños negocios de habla hispana que quieren trabajar mejor con herramientas digitales sin perder horas investigando.</p>

    <h2>Qué encontrarás</h2>
    <ul>
        <li><a href="{{ route('deals.index') }}">Ofertas</a>: cupones y descuentos verificados a mano.</li>
        <li><a href="{{ route('rankings.index') }}">Rankings</a>: las mejores apps de cada sector, ordenadas.</li>
        <li><a href="{{ route('explore') }}">Sectores</a>: fichas de apps con nota, precio y alternativas.</li>
        <li><a href="{{ route('posts.index') }}">Guías</a>: tutoriales, comparativas y explicaciones.</li>
    </ul>

    <h2>Cómo trabajamos</h2>
    <p>Cada publicación pasa por nuestro <a href="{{ route('pages.method') }}">método de prueba</a>. Algunos enlaces son de afiliado y siempre los señalamos; <a href="{{ route('pages.affiliates') }}">así funcionan</a>.</p>

    <p>¿Tienes una sugerencia o encontraste un error? <a href="{{ route('contact.create') }}">Escríbenos</a>.</p>
@endsection
