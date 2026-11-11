{{-- Self-contained on purpose: no database, Vite or session, so it renders even when the app is failing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', config('app.locale', 'es')) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#4cc3f0">
    <title>@yield('title') · DigitalNest</title>
    <style>
        @font-face { font-family: 'Archivo'; src: url('/fonts/archivo/archivo-latin-variable.woff2') format('woff2'); font-weight: 100 900; font-stretch: 62% 125%; font-display: swap; }
        *, *::before { box-sizing: border-box; }
        body { margin: 0; min-height: 100svh; display: grid; place-items: center; padding: 1.5rem; background: radial-gradient(50rem 30rem at 85% -10%, #b9ecff, transparent 60%), #4cc3f0; color: #0b2340; font-family: Archivo, ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        main { width: min(34rem, 100%); padding: clamp(1.75rem, 1rem + 4vw, 3rem); border-radius: 24px; background: #fff; box-shadow: 0 1px 2px #0b23400a, 0 32px 64px -32px #0b234059; }
        .brand { display: inline-flex; align-items: center; gap: .55rem; color: inherit; font-weight: 820; font-size: 1.15rem; letter-spacing: -.025em; text-decoration: none; }
        .code { margin: 2rem 0 0; color: #08739f; font-weight: 820; font-size: 4rem; line-height: 1; letter-spacing: -.05em; font-variant-numeric: tabular-nums; }
        h1 { margin: .5rem 0 0; font-weight: 820; font-size: clamp(1.6rem, 1.2rem + 2vw, 2.2rem); line-height: 1.05; letter-spacing: -.035em; text-wrap: balance; }
        p.text { margin: .9rem 0 0; color: #4a5b70; font-size: 1.05rem; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.75rem; }
        .btn { display: inline-flex; align-items: center; min-height: 48px; padding: 0 1.4rem; border-radius: 999px; background: #0b2340; color: #fff; font-weight: 700; text-decoration: none; }
        .btn:hover { background: #12345c; }
        .btn--light { background: #e6f6fd; color: #08739f; }
        .btn--light:hover { background: #d4f0fc; }
        a:focus-visible { outline: 3px solid #08739f; outline-offset: 3px; }
        ::selection { background: #4cc3f0; color: #0b2340; }
    </style>
</head>
<body>
    <main aria-labelledby="error-title">
        <a class="brand" href="{{ url('/') }}">
            <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><rect width="32" height="32" rx="9" fill="#4cc3f0"/><path d="M7 17.5c2.6 4.4 6 6.5 9 6.5s6.4-2.1 9-6.5" fill="none" stroke="#0b2340" stroke-width="2.6" stroke-linecap="round"/><path d="M11 13.5l3.4 3.4L21.5 9.5" fill="none" stroke="#0b2340" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="25" cy="8" r="2.4" fill="#ff7a45"/></svg>
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
                <a class="btn btn--light" href="{{ url('/herramientas') }}">Ver herramientas</a>
            @endif
        </div>
    </main>
</body>
</html>
