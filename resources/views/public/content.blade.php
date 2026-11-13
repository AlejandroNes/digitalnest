@extends('nest.layout')

@section('seo')
    <x-seo :content="$content" />
@endsection

@section('content')
    <div class="mx-auto flex max-w-2xl flex-col gap-6">
        <x-nest.page-header :title="$content->title ?: $content->name" :back="['url' => url('/'), 'label' => 'Inicio']" />
        @if(method_exists($content, 'image'))
            <x-content-image :media="$content->image" :alt="$content->imageAlt() ?? ''" :priority="true" />
        @endif
        <article class="nest-card nest-prose p-5 lg:p-8">{!! \App\Services\ContentHtml::sanitize($body) !!}</article>
    </div>
    <x-contact-assistant />
@endsection
