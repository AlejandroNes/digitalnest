@once
    @vite(['resources/css/contact-assistant.css', 'resources/js/contact-assistant.js'])
    <div {{ $attributes->merge(['class' => 'contact-assistant', 'data-contact-assistant' => '']) }}
        data-preview="{{ $configuration['preview'] ? 'true' : 'false' }}"
        data-config="{{ json_encode($configuration, JSON_THROW_ON_ERROR) }}"
        data-store-url="{{ route('contact-assistant.store', absolute: false) }}"
        data-config-url="{{ route('contact-assistant.show', absolute: false) }}">
        <button type="button" data-assistant-launch hidden class="ca-launch inline-flex items-center justify-center rounded-full" title="{{ $configuration['button_label'] }}" aria-haspopup="dialog" aria-controls="contact-assistant-dialog" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp ca-launch-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>
            <span class="sr-only">{{ $configuration['button_label'] }}</span>
        </button>
        <dialog id="contact-assistant-dialog" class="ca-dialog" aria-labelledby="contact-assistant-title" aria-describedby="contact-assistant-description">
            <div class="ca-shell flex flex-col">
                <header class="ca-header flex items-center justify-between gap-3 px-4 py-3">
                    <span class="ca-avatar" aria-hidden="true">
                        @if($configuration['avatar_url'])
                            <img src="{{ $configuration['avatar_url'] }}" width="200" height="200" alt="" class="ca-avatar-image">
                        @else
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 grow">
                        <h2 id="contact-assistant-title" class="text-base font-semibold">{{ $configuration['name'] }}</h2>
                        <p id="contact-assistant-description" class="ca-status mt-0.5 text-xs"><span class="sr-only">Asistente virtual</span><span class="ca-status-dot" aria-hidden="true"></span><span>En línea</span></p>
                    </div>
                    <div class="ca-header-actions flex items-center gap-1">
                        <button type="button" data-assistant-minimize class="ca-icon rounded-full p-2" aria-label="Minimizar asistente" title="Minimizar">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/></svg>
                        </button>
                        <button type="button" data-assistant-close class="ca-icon rounded-full p-2" aria-label="Cerrar y reiniciar asistente" title="Cerrar y reiniciar">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                </header>
                <div class="ca-content min-h-0 overflow-y-auto p-4" data-assistant-scroll>
                    <div data-assistant-history class="ca-history" role="region" aria-label="Conversación"></div>
                    <div data-assistant-typing hidden class="ca-typing" aria-hidden="true">
                        <span></span><span></span><span></span><small>Escribiendo…</small>
                    </div>
                </div>
                <p data-assistant-announcement class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
                <div data-assistant-composer class="ca-composer">
                    <div data-assistant-response class="ca-response p-4">
                        <div class="ca-step-status flex items-center gap-3">
                            <progress data-assistant-progress class="ca-progress grow" max="1" value="0" aria-label="Progreso de la consulta"></progress>
                            <span data-assistant-counter class="ca-counter ca-muted shrink-0 text-xs"></span>
                        </div>
                        <form data-assistant-form id="contact-assistant-form" novalidate aria-describedby="contact-assistant-error">
                            @csrf
                            <input type="hidden" name="submission_token" value="{{ $configuration['submission_token'] }}">
                            <div hidden aria-hidden="true"><label>Deja este campo vacío<input name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div data-assistant-fields>
                                @include('components.contact-assistant-fields', ['configuration' => $configuration])
                            </div>
                            <section data-step="consent" hidden aria-labelledby="ca-consent-question">
                                <h3 id="ca-consent-question" class="ca-question sr-only" tabindex="-1">Antes de continuar</h3>
                                <label class="mt-2 flex min-h-11 items-start gap-3 text-sm leading-relaxed cursor-pointer select-none">
                                    <input id="ca-consent" type="checkbox" name="consent" value="1" required aria-required="true" class="ca-checkbox mt-0.5 size-5 shrink-0">
                                    <span data-assistant-consent-text>{{ $configuration['consent_text'] }}</span>
                                </label>
                                <a data-assistant-privacy href="{{ $configuration['privacy_url'] ?? '' }}" @if(!$configuration['privacy_url']) hidden @endif target="_blank" rel="noopener noreferrer" class="ca-link mt-3 inline-block rounded py-2 text-sm underline">Política de Privacidad (nueva pestaña)</a>
                            </section>
                            <section data-step="summary" hidden aria-labelledby="ca-summary-question">
                                <h3 id="ca-summary-question" class="ca-summary-title" tabindex="-1">Revisa tus respuestas</h3>
                                <p id="ca-summary-description" class="ca-muted mt-1 text-sm">Comprueba que todo esté bien antes de continuar.</p>
                                <dl data-assistant-summary aria-describedby="ca-summary-description" class="ca-summary mt-4 text-sm"></dl>
                                <p class="ca-summary-note mt-3 rounded-xl px-3 py-3 text-xs leading-relaxed">{{ $configuration['preview'] ? 'Esta simulación no guarda consultas ni abre WhatsApp.' : 'Guardaremos tu consulta y prepararemos el mensaje. Debes confirmar el envío dentro de WhatsApp.' }}</p>
                            </section>
                            <p id="contact-assistant-error" data-assistant-error hidden class="ca-error mt-4 rounded-xl p-3 text-sm" role="alert" tabindex="-1"></p>
                        </form>
                        <section data-assistant-success hidden aria-labelledby="ca-success-title">
                            <div class="ca-success-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m6 12.5 4 4L18.5 8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <h3 id="ca-success-title" tabindex="-1" class="font-semibold">Tu consulta está guardada</h3>
                            <p data-assistant-final-message class="ca-muted mt-3 text-sm leading-relaxed"></p>
                            <a data-assistant-whatsapp class="ca-primary mt-5 block rounded-xl px-4 py-3 text-center text-sm font-semibold" @if($configuration['preview']) hidden @endif target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
                            <p @if($configuration['preview']) hidden @endif class="ca-muted mt-3 text-xs leading-relaxed">Si WhatsApp no se abrió, utiliza este enlace. El mensaje estará prellenado: todavía debes enviarlo dentro de WhatsApp.</p>
                        </section>
                    </div>
                    <div data-assistant-actions class="ca-actions flex flex-wrap items-center justify-between gap-2 px-4 pb-4">
                        <button data-assistant-skip type="button" hidden class="ca-secondary min-h-11 rounded-xl px-3 py-2 text-sm">Omitir</button>
                        <button data-assistant-back type="button" class="ca-secondary min-h-11 rounded-xl px-4 py-3 text-sm font-medium">Volver</button>
                        <button data-assistant-next type="{{ $configuration['preview'] ? 'button' : 'submit' }}" form="contact-assistant-form" class="ca-primary min-h-11 grow rounded-xl px-4 py-3 text-sm font-semibold">Continuar</button>
                    </div>
                </div>
            </div>
        </dialog>
    </div>
@endonce
