@props(['content' => null, 'title' => null, 'description' => null, 'noindex' => false])
@php
    $seo = app(\App\Services\Seo::class)->metadata(request(), $content);
    $seo['title'] = filled($title) ? $title : $seo['title'];
    $seo['description'] = filled($description) ? $description : $seo['description'];
    $seo['robots'] = $noindex ? 'noindex, follow' : $seo['robots'];
@endphp
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    @if($seo['image_alt'])
        <meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
        <meta name="twitter:image:alt" content="{{ $seo['image_alt'] }}">
    @endif
    @if($seo['image_width'] && $seo['image_height'])
        <meta property="og:image:width" content="{{ $seo['image_width'] }}">
        <meta property="og:image:height" content="{{ $seo['image_height'] }}">
    @endif
    @if($seo['image_mime'])
        <meta property="og:image:type" content="{{ $seo['image_mime'] }}">
    @endif
@endif
@if($seo['verification'])
    <meta name="google-site-verification" content="{{ $seo['verification'] }}">
@endif
