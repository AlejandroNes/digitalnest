<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', config('app.locale', 'es')) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('app.name', 'Sitio web') }}</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { margin: 0; background: #f9fafb; color: #111827; }
        main { box-sizing: border-box; display: flex; min-height: 100svh; align-items: center; justify-content: center; padding: 2rem 1rem; }
        section { width: 100%; max-width: 36rem; }
        h1 { font-size: clamp(1.75rem, 5vw, 2.5rem); line-height: 1.2; }
        p { line-height: 1.7; }
        .code { font-weight: 600; color: #4b5563; }
        a { display: inline-block; padding: .75rem 0; color: inherit; text-underline-offset: .25rem; }
        a:focus-visible { outline: 2px solid currentColor; outline-offset: 4px; }
        @media (prefers-color-scheme: dark) { body { background: #111827; color: #f9fafb; } .code { color: #d1d5db; } }
    </style>
</head>
<body>
    <main><section aria-labelledby="error-title">
        <p class="code">@yield('code')</p>
        <h1 id="error-title">@yield('title')</h1>
        <p>@yield('description')</p>
        <a href="{{ url('/') }}">Volver al inicio</a>
    </section></main>
</body>
</html>
