{{-- Shared shell for fixed pages. Expects $title, $lede and a "body" section. --}}
@extends('public.layout')

@section('content')
    <div class="dn-page-head dn-page-head--sky">
        <div class="dn-wrap">
            <nav class="dn-crumbs" aria-label="Migas de pan">
                <a href="{{ url('/') }}">Inicio</a> <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $title }}</span>
            </nav>
            <h1 class="dn-display dn-title" style="margin-top:1rem">{{ $title }}</h1>
            <p class="dn-lede">{{ $lede }}</p>
        </div>
    </div>
    <div class="dn-wrap">
        <article class="dn-article">
            <div class="dn-prose">
                @yield('body')
            </div>
        </article>
    </div>
@endsection
