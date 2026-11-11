<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCookieConsentRequest;
use App\Services\CookieConsent;
use App\Services\InternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class CookieConsentController extends Controller
{
    public function __invoke(StoreCookieConsentRequest $request, CookieConsent $consent): RedirectResponse
    {
        $statistics = match ($request->validated('decision')) {
            'accept' => true,
            'reject' => false,
            default => $request->boolean('statistics'),
        };
        $marketing = match ($request->validated('decision')) {
            'accept' => true, 'reject' => false, default => $request->boolean('marketing'),
        };
        $preferences = match ($request->validated('decision')) {
            'accept' => true, 'reject' => false, default => $request->boolean('preferences'),
        };
        $cookie = Cookie::make(CookieConsent::NAME, json_encode($consent->payload($statistics, $marketing, $preferences), JSON_THROW_ON_ERROR), CookieConsent::DAYS * 24 * 60,
            '/', null, $request->isSecure(), true, false, 'lax');
        $response = redirect(InternalUrl::absolute($request->validated('return_to') ?: '/'))->withCookie($cookie);
        if (! $statistics) {
            foreach (array_keys($request->cookies->all()) as $name) {
                if (preg_match('/^(_ga(?:_|$)|_gid$|_gat(?:_|$))/', $name)) {
                    $response->withCookie(Cookie::forget($name));
                }
            }
        }
        if (! $marketing) {
            foreach (array_keys($request->cookies->all()) as $name) {
                if (preg_match('/^(_gcl_|_fbp$|_fbc$)/', $name)) {
                    $response->withCookie(Cookie::forget($name));
                }
            }
        }

        return $response;
    }
}
