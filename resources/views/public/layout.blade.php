<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-integrations position="head" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4cc3f0">
    @yield('seo')
    @stack('structured-data')
    <link rel="preload" href="/fonts/poppins/poppins-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/poppins/poppins-700.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dn-site">
    <x-site-integrations position="body_start" />
    <a class="dn-skip" href="#contenido">Saltar al contenido</a>

    <header class="dn-header" data-header>
        <div class="dn-wrap dn-header-row">
            <a class="dn-brand" href="{{ url('/') }}">
                @include('public.partials.brand-mark')
                DigitalNest
            </a>
            <button class="dn-menu-toggle" type="button" aria-controls="menu-principal" aria-expanded="false" data-menu-toggle>
                <span class="dn-visually-hidden">Menú</span>
                <svg class="dn-icon-open" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg class="dn-icon-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <nav class="dn-nav" id="menu-principal" aria-label="Principal">
                <a href="{{ route('tools.index') }}" @if(request()->routeIs('tools.*')) aria-current="page" @endif>Herramientas</a>
                <a href="{{ route('workflows.index') }}" @if(request()->routeIs('workflows.*')) aria-current="page" @endif>Flujos</a>
                <a href="{{ route('templates.index') }}" @if(request()->routeIs('templates.*')) aria-current="page" @endif>Plantillas</a>
                <a href="{{ route('posts.index') }}" @if(request()->routeIs('posts.*')) aria-current="page" @endif>Guías</a>
                <a class="dn-nav-search" href="{{ route('search') }}" aria-label="Buscar" @if(request()->routeIs('search')) aria-current="page" @endif>@include('public.partials.icon', ['name' => 'search', 'size' => 18])</a>
            </nav>
        </div>
    </header>

    <main id="contenido">
        @yield('content')
    </main>

    <footer class="dn-footer">
        <div class="dn-wrap dn-footer-grid">
            <div>
                <p class="dn-brand">@include('public.partials.brand-mark') DigitalNest</p>
                <p class="dn-footer-note">Probamos herramientas digitales y de IA para que elijas con datos: precio real, plan gratis y cómo funcionan en español. Algunos enlaces son de afiliado y siempre están señalados; <a href="{{ route('pages.affiliates') }}" style="text-decoration:underline">no cambian nuestro veredicto</a>.</p>
            </div>
            <nav aria-label="Pie de página">
                <ul>
                    <li><a href="{{ route('tools.index') }}">Herramientas</a></li>
                    <li><a href="{{ route('workflows.index') }}">Flujos de trabajo</a></li>
                    <li><a href="{{ route('templates.index') }}">Plantillas</a></li>
                    <li><a href="{{ route('posts.index') }}">Guías</a></li>
                    <li><a href="{{ route('pages.about') }}">Sobre DigitalNest</a></li>
                    <li><a href="{{ route('pages.method') }}">Cómo probamos</a></li>
                    <li><a href="{{ route('pages.affiliates') }}">Afiliados</a></li>
                    <li><a href="{{ route('contact.create') }}">Contacto</a></li>
                    <li><a href="{{ route('legal.show', ['type' => 'privacy']) }}">Privacidad</a></li>
                    <li><a href="{{ route('legal.show', ['type' => 'terms']) }}">Términos</a></li>
                </ul>
            </nav>
        </div>
        <p class="dn-wrap dn-footer-legal">© {{ now()->year }} DigitalNest</p>
    </footer>

    <x-cookie-consent />
    <x-site-integrations position="body_end" />
</body>
</html>
