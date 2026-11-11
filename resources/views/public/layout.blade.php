<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-integrations position="head" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4cc3f0">
    @yield('seo')
    @stack('structured-data')
    <link rel="preload" href="/fonts/archivo/archivo-latin-variable.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dn-site">
    <x-site-integrations position="body_start" />
    <a class="dn-skip" href="#contenido">Saltar al contenido</a>

    <header class="dn-header">
        <div class="dn-wrap dn-header-row">
            <a class="dn-brand" href="{{ url('/') }}">
                @include('public.partials.brand-mark')
                DigitalNest
            </a>
            <nav class="dn-nav" aria-label="Principal">
                <a href="{{ route('tools.index') }}" @if(request()->routeIs('tools.*')) aria-current="page" @endif>Herramientas</a>
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
                <p class="dn-footer-note">Probamos herramientas digitales y de IA para que elijas con datos: precio real, plan gratis y cómo funcionan en español. Algunos enlaces son de afiliado y siempre están señalados; no cambian nuestro veredicto.</p>
            </div>
            <nav aria-label="Pie de página">
                <ul>
                    <li><a href="{{ route('tools.index') }}">Herramientas</a></li>
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
