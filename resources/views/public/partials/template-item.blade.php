<li class="dn-item dn-item--split">
    <div>
        <a class="dn-item-title" href="{{ route('templates.show', ['slug' => $template->slug]) }}">{{ $template->title }}</a>
        @if($template->is_featured)
            <span class="dn-tag">Recomendada</span>
        @endif
        <p class="dn-tool-tagline">{{ $template->summary }}</p>
    </div>
    <p class="dn-meta">
        <span class="dn-pill dn-pill--sky">{{ $template->type?->getLabel() }}</span>
        @foreach($template->tools->take(3) as $tool)
            <span class="dn-pill">{{ $tool->name }}</span>
        @endforeach
    </p>
</li>
