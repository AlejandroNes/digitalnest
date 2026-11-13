{{-- Self-contained on purpose: no database, Vite or session, so it renders even when the app is failing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', config('app.locale', 'es')) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#09090d">
    <title>@yield('title') · DigitalNest</title>
    <style>
        @font-face { font-family: 'Poppins'; src: url('/fonts/poppins/poppins-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'Poppins'; src: url('/fonts/poppins/poppins-700.woff2') format('woff2'); font-weight: 700; font-display: swap; }
        *, *::before { box-sizing: border-box; }
        body { margin: 0; min-height: 100svh; display: grid; place-items: center; padding: 1.25rem; background: radial-gradient(40rem 26rem at 90% -10%, #8b6cff55, transparent 60%), #09090d; color: #f3f3f7; font-family: Poppins, ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        main { width: min(32rem, 100%); padding: clamp(1.5rem, 1rem + 4vw, 2.75rem); border: 1px solid #ffffff14; border-radius: 28px; background: #13131a; box-shadow: 0 24px 64px -24px #000c; }
        .brand { display: inline-flex; align-items: center; gap: .6rem; color: inherit; font-weight: 700; font-size: 1.1rem; letter-spacing: -.02em; text-decoration: none; }
        .brand img { width: 32px; height: 32px; border-radius: 9px; }
        .code { margin: 2rem 0 0; color: #8b6cff; font-weight: 700; font-size: 4rem; line-height: 1; letter-spacing: -.05em; font-variant-numeric: tabular-nums; }
        h1 { margin: .5rem 0 0; font-weight: 700; font-size: clamp(1.5rem, 1.2rem + 2vw, 2.1rem); line-height: 1.1; letter-spacing: -.03em; text-wrap: balance; }
        p.text { margin: .9rem 0 0; color: #9a9ab0; font-size: 1rem; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.75rem; }
        .btn { display: inline-flex; align-items: center; min-height: 48px; padding: 0 1.4rem; border-radius: 999px; background: #8b6cff; color: #fff; font-weight: 700; text-decoration: none; }
        .btn:hover { background: #7a59ff; }
        .btn--light { background: #1b1b24; color: #f3f3f7; border: 1px solid #ffffff14; }
        .btn--light:hover { background: #24242f; }
        a:focus-visible { outline: 2px solid #8b6cff; outline-offset: 3px; }
    </style>
</head>
<body>
    <main aria-labelledby="error-title">
        <a class="brand" href="{{ url('/') }}">
            <img src="/icons/icon-192.png" alt="">
            DigitalNest
        </a>
        <p class="code" aria-hidden="true">@yield('code')</p>
        <h1 id="error-title">@yield('title')</h1>
        <p class="text">@yield('description')</p>
        <div class="actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a class="btn" href="{{ url('/') }}">Volver al inicio</a>
                <a class="btn btn--light" href="{{ url('/ofertas') }}">Ver ofertas</a>
            @endif
        </div>
    </main>
</body>
</html>
