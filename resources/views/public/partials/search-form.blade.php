<form class="dn-search {{ $class ?? '' }}" method="GET" action="{{ route('search') }}" role="search">
    <label class="dn-visually-hidden" for="{{ $id ?? 'buscar' }}">Buscar en DigitalNest</label>
    <span class="dn-search-icon" aria-hidden="true">@include('public.partials.icon', ['name' => 'search', 'size' => 20])</span>
    <input id="{{ $id ?? 'buscar' }}" class="dn-search-input" type="search" name="q" value="{{ $value ?? '' }}" maxlength="100" placeholder="{{ $placeholder ?? 'Ej.: video, n8n, prompts…' }}" autocomplete="off">
    <button class="dn-btn dn-btn--sm" type="submit">Buscar</button>
</form>
