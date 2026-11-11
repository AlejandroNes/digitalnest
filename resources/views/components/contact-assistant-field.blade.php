<section data-step="{{ $field['id'] }}" @if(!$first) hidden @endif aria-labelledby="ca-{{ $field['id'] }}-question">
    <h3 id="ca-{{ $field['id'] }}-question" class="ca-question sr-only" tabindex="-1">{{ $field['question'] }}</h3>
    @if($field['id'] === 'whatsapp')
        <details data-assistant-country-picker class="ca-country-picker">
            <summary class="ca-country-trigger" aria-label="Cambiar país y prefijo"><span data-assistant-country-label>Elegir país y prefijo</span><svg class="ca-chevron shrink-0" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
            <div class="ca-country-menu">
                <label for="ca-country-search" class="ca-muted block text-sm">Buscar país o prefijo</label>
                <input id="ca-country-search" data-assistant-country-search type="search" class="ca-field mt-2 w-full rounded-xl border px-3 py-2 text-base" autocomplete="off" placeholder="Bolivia, España, +591…">
                <label for="ca-phone-country" class="sr-only">País y prefijo</label>
                <select id="ca-phone-country" name="phone_country" size="4" class="ca-country-options ca-field mt-2 w-full rounded-xl border text-base" aria-describedby="ca-country-help">
                    @foreach($configuration['phone_countries'] as $country)
                        <option value="{{ $country['code'] }}" @selected($country['code'] === $configuration['default_phone_country'])>{{ $country['flag'] }} {{ $country['name'] }} +{{ $country['dial_code'] }}</option>
                    @endforeach
                </select>
                <p data-assistant-country-empty hidden class="ca-muted mt-2 text-sm" role="status">No encontramos ese país. Prueba con el nombre o el prefijo.</p>
                <p id="ca-country-help" class="ca-muted mt-2 text-xs">El país sugerido depende del idioma del navegador. Puedes cambiarlo.</p>
            </div>
        </details>
        <label for="ca-whatsapp" class="ca-muted mt-2 block text-sm font-medium">{{ $field['label'] }}</label>
        <input id="ca-whatsapp" name="whatsapp" type="tel" inputmode="tel" class="ca-field mt-2 w-full rounded-xl border px-3 py-3 text-base" autocomplete="tel-national" required maxlength="40" aria-describedby="ca-phone-help" aria-required="true">
        <p id="ca-phone-help" data-assistant-phone-preview class="ca-muted mt-2 text-xs" aria-live="off">Escribe tu número nacional. También puedes pegarlo con + y el prefijo.</p>
    @elseif($field['type'] === 'service')
        <label for="ca-service" class="ca-muted block text-sm font-medium">{{ $field['label'] }} (opcional)</label>
        <select id="ca-service" name="service_id" hidden aria-hidden="true" tabindex="-1">
            <option value="">Necesito orientación</option>
            @foreach($configuration['services'] as $service)
                <option value="{{ $service['id'] }}">{{ $service['name'] }}</option>
            @endforeach
        </select>
        <div data-assistant-services class="ca-services mt-2" role="group" aria-labelledby="ca-{{ $field['id'] }}-question">
            @foreach($configuration['services'] as $service)
                <button type="button" data-service-value="{{ $service['id'] }}" aria-pressed="false" class="ca-service">{{ $service['name'] }}</button>
            @endforeach
            <button type="button" data-service-value="" aria-pressed="true" class="ca-service">Necesito orientación</button>
        </div>
        @if(!$configuration['services'])<p class="ca-muted mt-2 text-sm">Puedes continuar con una consulta general.</p>@endif
    @elseif($field['type'] === 'choice')
        <input type="hidden" name="{{ $field['id'] }}" value="">
        <div class="ca-services" role="group" aria-labelledby="ca-{{ $field['id'] }}-question">
            @foreach($field['options'] as $option)
                <button type="button" data-option-field="{{ $field['id'] }}" data-option-value="{{ $option['value'] }}" aria-pressed="false" class="ca-service">{{ $option['label'] }}</button>
            @endforeach
        </div>
    @elseif($field['type'] === 'multiple')
        <fieldset class="ca-services">
            <legend class="sr-only">{{ $field['question'] }}</legend>
            @foreach($field['options'] as $option)
                <label class="ca-service flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="{{ $field['id'] }}" value="{{ $option['value'] }}" class="ca-checkbox size-5 shrink-0">
                    <span>{{ $option['label'] }}</span>
                </label>
            @endforeach
        </fieldset>
    @else
        <label for="ca-{{ $field['id'] }}" @if($field['id'] === 'email') data-assistant-email-label @endif class="ca-muted block text-sm font-medium">{{ $field['label'] }}{{ $field['required'] ? '' : ' (opcional)' }}</label>
        @if($field['type'] === 'textarea')
            <textarea id="ca-{{ $field['id'] }}" name="{{ $field['id'] }}" class="ca-field mt-2 w-full rounded-xl border px-3 py-3 text-base" rows="3" minlength="{{ $field['min_length'] }}" maxlength="{{ $field['max_length'] }}" @required($field['required']) aria-required="{{ $field['required'] ? 'true' : 'false' }}"></textarea>
        @else
            <input id="ca-{{ $field['id'] }}" name="{{ $field['id'] }}" type="{{ match($field['type']) { 'phone' => 'tel', 'email' => 'email', 'number' => 'number', 'date' => 'date', default => 'text' } }}"
                @if($field['type'] === 'phone') inputmode="tel" autocomplete="tel" placeholder="+591 77777777" maxlength="40"
                @elseif($field['type'] === 'number') inputmode="decimal" step="any" @if($field['min_value'] !== null) min="{{ $field['min_value'] }}" @endif @if($field['max_value'] !== null) max="{{ $field['max_value'] }}" @endif
                @elseif($field['type'] === 'date') @if($field['min_date']) min="{{ $field['min_date'] }}" @endif @if($field['max_date']) max="{{ $field['max_date'] }}" @endif
                @else minlength="{{ $field['min_length'] }}" maxlength="{{ $field['max_length'] }}" autocomplete="{{ $field['id'] === 'name' ? 'name' : ($field['type'] === 'email' ? 'email' : 'off') }}" @endif
                class="ca-field mt-2 w-full rounded-xl border px-3 py-3 text-base" @required($field['required']) aria-required="{{ $field['required'] ? 'true' : 'false' }}">
        @endif
        @if($field['type'] === 'phone')<p class="ca-muted mt-2 text-xs">Incluye + y el código de país.</p>@endif
    @endif
    @if($field['help'])<p class="ca-muted mt-2 text-xs">{{ $field['help'] }}</p>@endif
</section>
