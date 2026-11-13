@php
    $navigation = [
        ['label' => 'Inicio', 'path' => '/', 'icon' => 'home'],
        ['label' => 'Explorar', 'path' => 'explorar', 'icon' => 'squares-2x2', 'also' => ['sector/*', 'app/*']],
        ['label' => 'Ofertas', 'path' => 'ofertas', 'icon' => 'ticket', 'also' => ['oferta/*']],
        ['label' => 'Rankings', 'path' => 'top', 'icon' => 'trophy'],
        ['label' => 'Guardados', 'path' => 'guardados', 'icon' => 'bookmark'],
    ];
    $isCurrent = fn (array $item): bool => $item['path'] === '/' ? request()->is('/') : request()->is($item['path'], $item['path'].'/*', ...($item['also'] ?? []));
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <x-site-integrations position="head" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#09090d">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="DigitalNest">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script>
        try { const t = localStorage.getItem('nest-theme'); if (t === 'light' || t === 'dark') { document.documentElement.dataset.theme = t; } } catch (e) {}
    </script>
    @yield('seo')
    @stack('structured-data')
    <link rel="preload" href="/fonts/poppins/poppins-600.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/nest.css', 'resources/js/nest.js'])
</head>
<body class="min-h-dvh">
    <x-site-integrations position="body_start" />
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-accent focus:px-4 focus:py-2 focus:text-accent-ink">Saltar al contenido</a>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-line bg-surface/60 px-4 py-6 backdrop-blur-xl lg:flex">
        <a href="{{ url('/') }}" class="mb-8 flex items-center gap-2.5 px-2 text-lg font-semibold tracking-tight">
            <span class="grid size-9 place-items-center rounded-xl bg-accent text-accent-ink shadow-card"><x-heroicon-s-sparkles class="size-5" /></span>
            DigitalNest
        </a>
        <nav aria-label="Principal" class="flex flex-col gap-1">
            @foreach ($navigation as $item)
                <a href="{{ url($item['path']) }}" @if ($isCurrent($item)) aria-current="page" @endif
                   class="nest-pressable flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-muted hover:bg-raised hover:text-ink aria-[current=page]:bg-accent-soft aria-[current=page]:text-accent">
                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-5" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="lg:pl-60">
        {{-- Top bar --}}
        <header class="nest-glass pt-safe sticky top-0 z-20 border-b border-line">
            <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4 lg:h-16 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold tracking-tight lg:hidden">
                    <span class="grid size-8 place-items-center rounded-lg bg-accent text-accent-ink"><x-heroicon-s-sparkles class="size-4" /></span>
                    DigitalNest
                </a>
                <a href="{{ route('search') }}" class="nest-pressable ml-auto flex h-10 items-center gap-2 rounded-full border border-line bg-raised px-3 text-sm text-muted hover:text-ink lg:ml-0 lg:w-full lg:max-w-md lg:px-4">
                    <x-heroicon-o-magnifying-glass class="size-5" />
                    <span class="hidden lg:inline">Busca apps, cupones o sectores…</span>
                    <span class="sr-only lg:hidden">Buscar</span>
                </a>
                <button type="button" data-theme-toggle class="nest-pressable grid size-10 place-items-center rounded-full border border-line bg-raised text-muted hover:text-ink lg:ml-auto" aria-label="Cambiar tema claro u oscuro">
                    <x-heroicon-o-sun class="hidden size-5 dark:block" />
                    <x-heroicon-o-moon class="size-5 dark:hidden" />
                </button>
            </div>
        </header>

        <main id="contenido" class="overflow-x-clip mx-auto max-w-6xl px-4 pt-5 pb-28 lg:px-8 lg:pt-8 lg:pb-16">
            @yield('content')

            <footer class="mt-14 flex flex-col gap-4 border-t border-line pt-6 text-sm text-muted">
                <nav aria-label="Pie de página">
                    <ul class="flex flex-wrap gap-x-5 gap-y-2">
                        <li><a class="hover:text-ink" href="{{ route('posts.index') }}">Guías</a></li>
                        <li><a class="hover:text-ink" href="{{ route('pages.about') }}">Sobre DigitalNest</a></li>
                        <li><a class="hover:text-ink" href="{{ route('pages.method') }}">Cómo probamos</a></li>
                        <li><a class="hover:text-ink" href="{{ route('pages.affiliates') }}">Afiliados</a></li>
                        <li><a class="hover:text-ink" href="{{ route('contact.create') }}">Contacto</a></li>
                        <li><a class="hover:text-ink" href="{{ route('legal.show', ['type' => 'privacy']) }}">Privacidad</a></li>
                        <li><a class="hover:text-ink" href="{{ route('legal.show', ['type' => 'terms']) }}">Términos</a></li>
                    </ul>
                </nav>
                <x-cookie-consent variant="nest" />
                <p class="text-xs">Algunos enlaces son de afiliado y están señalados; no cambian nuestro veredicto. © {{ now()->year }} DigitalNest</p>
            </footer>
        </main>
    </div>

    {{-- Mobile bottom navigation --}}
    <nav aria-label="Principal" class="nest-glass pb-safe fixed inset-x-0 bottom-0 z-30 border-t border-line lg:hidden">
        <ul class="mx-auto grid max-w-md grid-cols-5">
            @foreach ($navigation as $item)
                <li>
                    <a href="{{ url($item['path']) }}" @if ($isCurrent($item)) aria-current="page" @endif
                       class="nest-pressable group flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-muted aria-[current=page]:text-accent">
                        <span class="grid h-7 w-12 place-items-center rounded-full group-aria-[current=page]:bg-accent-soft">
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-[22px]" />
                        </span>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div data-install-prompt hidden class="nest-glass fixed inset-x-3 bottom-24 z-40 mx-auto flex max-w-md items-center gap-3 rounded-2xl border border-line p-3 shadow-card lg:bottom-6">
        <img src="/icons/icon-192.png" alt="" class="size-11 rounded-xl">
        <p class="min-w-0 flex-1 text-sm">
            <span class="block font-semibold">Instala DigitalNest</span>
            <span class="block text-muted" data-install-hint>Acceso directo y carga más rápida.</span>
        </p>
        <button type="button" data-install-accept class="nest-pressable h-9 shrink-0 rounded-full bg-accent px-4 text-sm font-semibold text-accent-ink">Instalar</button>
        <button type="button" data-install-dismiss class="grid size-9 shrink-0 place-items-center rounded-full text-muted hover:text-ink" aria-label="Cerrar">
            <x-heroicon-o-x-mark class="size-5" />
        </button>
    </div>

    <div data-toast data-visible="false" role="status" aria-live="polite"
         class="nest-glass pointer-events-none fixed bottom-24 left-1/2 z-40 -translate-x-1/2 rounded-full border border-line px-4 py-2.5 text-sm font-medium shadow-card transition duration-200 data-[visible=false]:translate-y-3 data-[visible=false]:opacity-0 lg:bottom-8"></div>

    <x-site-integrations position="body_end" />
</body>
</html>
