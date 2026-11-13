{{-- Shared shell for fixed pages. Expects $title, $lede and a "body" section. --}}
@extends('nest.layout')

@section('content')
    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <x-nest.page-header :title="$title" :subtitle="$lede" :back="['url' => url('/'), 'label' => 'Inicio']" />
        <article class="nest-card nest-prose p-5 lg:p-8">
            @yield('body')
        </article>
    </div>
@endsection
