<!DOCTYPE html>
<html lang="es">
<head>
    <x-site-integrations position="head" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 font-sans text-gray-900 antialiased">
    <x-site-integrations position="body_start" />
    <main class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
        <livewire:portfolio :initial-project-slug="$initialProjectSlug" />
    </main>
    <x-cookie-consent />
    @livewireScripts
    <x-site-integrations position="body_end" />
</body>
</html>
