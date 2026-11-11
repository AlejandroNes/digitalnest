@once
    @php
        $settings = \App\Models\SearchSetting::current();
        $consent = app(\App\Services\CookieConsent::class);
        $decision = $consent->decision(request(), $settings);
        $privacy = $consent->policyRevision() > 0;
    @endphp
    @if($settings->cookies_enabled)
        <section data-cookie-banner aria-label="Preferencias de cookies" class="border-t border-gray-300 bg-white p-4 text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
            <details @if(!$decision) open @endif>
                <summary class="cursor-pointer rounded p-2 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">Preferencias de cookies</summary>
                <p class="my-3">{{ $settings->cookie_message ?: 'Utilizamos cookies necesarias para el funcionamiento del sitio. La analítica, publicidad y funcionalidades opcionales solo se activan según tus preferencias.' }}</p>
                @if($privacy)
                    <a href="{{ route('legal.show', ['type' => 'privacy']) }}" class="underline focus-visible:outline-2">Política de Privacidad</a>
                @endif
                <form method="POST" action="{{ route('cookies.store') }}" data-cookie-consent class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ '/'.ltrim(request()->path(), '/') }}">
                    <label class="flex items-center gap-2"><input type="checkbox" checked disabled> Necesarias (siempre activas)</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="statistics" value="1" @checked($decision['statistics'] ?? false)> Estadísticas</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="marketing" value="1" @checked($decision['marketing'] ?? false)> Publicidad</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="preferences" value="1" @checked($decision['preferences'] ?? false)> Funcionalidades opcionales</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['accept' => 'Aceptar opcionales', 'reject' => 'Rechazar opcionales', 'save' => 'Guardar preferencias'] as $value => $label)
                            <button type="submit" name="decision" value="{{ $value }}" class="rounded border border-current px-4 py-2 focus-visible:outline-2 focus-visible:outline-offset-2">{{ $label }}</button>
                        @endforeach
                    </div>
                </form>
            </details>
        </section>
    @endif
    <script @if($nonce = app(\App\Services\SiteIntegrations::class)->nonce(request())) nonce="{{ $nonce }}" @endif>
        (() => {
            if (window.__corporateConsentInitialized) return;
            window.__corporateConsentInitialized = true;
            const id = {{ \Illuminate\Support\Js::from($settings->validMeasurementId()) }};
            const allowed = {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'statistics', $settings)) }};
            const marketingAllowed = {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'marketing', $settings)) }};
            const clearCookies = pattern => {
                const domains = location.hostname.split('.');
                const paths = location.pathname.split('/');
                const cookiePaths = ['/'];
                for (let i = 1; i < paths.length; i++) cookiePaths.push(paths.slice(0, i + 1).join('/'));
                document.cookie.split(';').forEach(cookie => {
                    const name = cookie.split('=')[0].trim();
                    if (!pattern.test(name)) return;
                    cookiePaths.forEach(path => {
                        document.cookie = name + '=; Max-Age=0; path=' + path;
                        for (let i = 0; i < domains.length; i++) {
                            document.cookie = name + '=; Max-Age=0; path=' + path + '; domain=.' + domains.slice(i).join('.');
                        }
                    });
                });
            };
            const revoke = () => {
                if (id) window['ga-disable-' + id] = true;
                if (window.gtag) window.gtag('consent', 'update', {analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'});
                clearCookies(/^(_ga(?:_|$)|_gid$|_gat(?:_|$)|_gcl_|_fbp$|_fbc$)/);
            };
            const denyStatistics = () => {
                if (id) window['ga-disable-' + id] = true;
                if (window.gtag) window.gtag('consent', 'update', {analytics_storage: 'denied'});
                clearCookies(/^(_ga(?:_|$)|_gid$|_gat(?:_|$))/);
            };
            const denyMarketing = () => {
                if (window.gtag) window.gtag('consent', 'update', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'});
                clearCookies(/^(_gcl_|_fbp$|_fbc$)/);
            };
            if (!allowed && !marketingAllowed) revoke();
            else if (!allowed) denyStatistics();
            else if (!marketingAllowed) clearCookies(/^(_gcl_|_fbp$|_fbc$)/);
            document.addEventListener('submit', event => {
                const form = event.target;
                if (!form.matches('[data-cookie-consent]')) return;
                const choice = event.submitter?.value || 'save';
                const statistics = form.elements.statistics.checked;
                const marketing = form.elements.marketing?.checked ?? false;
                if (choice === 'reject' || (choice === 'save' && !statistics && !marketing)) revoke();
                else if (choice === 'save') {
                    if (!statistics) denyStatistics();
                    if (!marketing && marketingAllowed) denyMarketing();
                }
            });
        })();
    </script>
@endonce
