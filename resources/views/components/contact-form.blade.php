<form {{ $attributes->except(['method', 'action']) }} method="POST" action="{{ route('contact.store', [], false) }}">
    @csrf
    <input type="hidden" name="submission_token" value="{{ $submissionToken() }}">
    <input type="hidden" name="return_to" value="{{ '/'.ltrim(request()->path(), '/') }}">
    @if(session('contact_success'))
        <p role="status">{{ session('contact_success') }}</p>
    @endif
    @if(session('contact_error'))
        <p role="alert">{{ session('contact_error') }}</p>
    @endif
    @if($errors->any())
        <div role="alert">
            <p>Revisa los campos del formulario.</p>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @php($formId = 'contact-'.\Illuminate\Support\Str::uuid())
    <div><label for="{{ $formId }}-name">Nombre completo</label><input id="{{ $formId }}-name" name="name" autocomplete="name" required minlength="2" maxlength="150"></div>
    <div><label for="{{ $formId }}-email">Correo electrónico</label><input id="{{ $formId }}-email" name="email" type="email" autocomplete="email" required maxlength="255"></div>
    <div><label for="{{ $formId }}-phone">Teléfono (opcional)</label><input id="{{ $formId }}-phone" name="phone" type="tel" autocomplete="tel" maxlength="40"></div>
    <div><label for="{{ $formId }}-subject">Asunto (opcional)</label><input id="{{ $formId }}-subject" name="subject" maxlength="200"></div>
    <div><label for="{{ $formId }}-message">Mensaje</label><textarea id="{{ $formId }}-message" name="message" required minlength="10" maxlength="10000" rows="6"></textarea></div>
    <div hidden aria-hidden="true"><label>Deja este campo vacío<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <button type="submit">Enviar consulta</button>
    {{ $slot }}
</form>
