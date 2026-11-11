@props(['position' => 'head'])
@once('site-integrations-'.$position)
    @php
        $integrations = app(\App\Services\SiteIntegrations::class);
        $settings = \App\Models\SearchSetting::current();
        $consent = app(\App\Services\CookieConsent::class);
        $nonce = $integrations->nonce(request());
        $gtm = $integrations->tagManagerAllowed(request(), $settings);
        $ga = !$settings->usesTagManager() && $consent->allowsStatistics(request(), $settings);
    @endphp
    @if($integrations->isPublic(request()))
        @if($position === 'head' && ($gtm || $ga))
            <script @if($nonce) nonce="{{ $nonce }}" @endif>
                (() => {
                    window.dataLayer = window.dataLayer || [];
                    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
                    window.gtag('consent', 'default', {
                        analytics_storage: {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'statistics', $settings) ? 'granted' : 'denied') }},
                        ad_storage: {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'marketing', $settings) ? 'granted' : 'denied') }},
                        ad_user_data: {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'marketing', $settings) ? 'granted' : 'denied') }},
                        ad_personalization: {{ \Illuminate\Support\Js::from($consent->allowsPurpose(request(), 'marketing', $settings) ? 'granted' : 'denied') }}
                    });
                    @if($gtm)
                        if (window.__corporateGtmInitialized) return;
                        window.__corporateGtmInitialized = true;
                        const id = {{ \Illuminate\Support\Js::from($settings->validTagManagerId()) }};
                        window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
                        const first = document.getElementsByTagName('script')[0];
                        const script = document.createElement('script');
                        script.async = true;
                        script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
                        if (document.currentScript?.nonce) script.setAttribute('nonce', document.currentScript.nonce);
                        first.parentNode.insertBefore(script, first);
                    @else
                        if (window.__corporateGaInitialized) return;
                        window.__corporateGaInitialized = true;
                        const id = {{ \Illuminate\Support\Js::from($settings->validMeasurementId()) }};
                        window.gtag('js', new Date());
                        window.gtag('config', id);
                        const script = document.createElement('script');
                        script.async = true;
                        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
                        if (document.currentScript?.nonce) script.setAttribute('nonce', document.currentScript.nonce);
                        document.head.appendChild(script);
                    @endif
                })();
            </script>
        @endif
        @if($position === 'body_start' && $gtm)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $settings->validTagManagerId() }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
        @endif
        {!! $integrations->customMarkup(request(), $position, $settings) !!}
    @endif
@endonce
