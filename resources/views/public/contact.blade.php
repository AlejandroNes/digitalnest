<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-integrations position="head" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <x-site-integrations position="body_start" />
    <main><h1>Contacto</h1><x-contact-form /></main>
    <x-cookie-consent />
    <x-contact-assistant />
    <x-site-integrations position="body_end" />
</body>
</html>
