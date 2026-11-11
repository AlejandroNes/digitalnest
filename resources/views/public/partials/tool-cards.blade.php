<div class="dn-cards">
    @foreach($tools as $tool)
        @include('public.partials.tool-card', ['tool' => $tool])
    @endforeach
</div>
