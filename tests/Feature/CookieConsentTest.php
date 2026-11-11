<?php

use App\Models\LegalPage;
use App\Models\SearchSetting;
use App\Services\CookieConsent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://corporate.test']);
    SearchSetting::factory()->create(['analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567']);
});

it('blocks all Google requests before consent and offers equal acceptance rejection and privacy access', function (): void {
    LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false)->assertDontSee("gtag('config'", false)
        ->assertSee('Aceptar opcionales')->assertSee('Rechazar opcionales')->assertSee('Guardar preferencias')
        ->assertSee('/legal/privacy', false)->assertSee('Necesarias (siempre activas)');
});

it('persists acceptance in an encrypted secure HTTP only cookie and then loads analytics', function (): void {
    $response = $this->post('https://corporate.test/cookies/consent', ['decision' => 'accept', 'return_to' => '/'])->assertRedirect('https://corporate.test/');
    $encrypted = $response->getCookie(CookieConsent::NAME, false);
    expect($encrypted->isHttpOnly())->toBeTrue();
    expect($encrypted->isSecure())->toBeTrue();
    expect($encrypted->getSameSite())->toBe('lax');
    expect($encrypted->getValue())->not->toContain('statistics');
    $cookie = $response->getCookie(CookieConsent::NAME)->getValue();
    expect(json_decode($cookie, true)['statistics'])->toBeTrue();
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->assertSee('googletagmanager.com', false)->assertSee("window.gtag('config', id)", false);
});

it('rejects optional cookies regardless of a tampered statistics checkbox and supports revocation', function (): void {
    $response = $this->post('/cookies/consent', ['decision' => 'reject', 'statistics' => 1])->assertRedirect();
    $cookie = $response->getCookie(CookieConsent::NAME)->getValue();
    expect(json_decode($cookie, true)['statistics'])->toBeFalse();
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->assertDontSee('googletagmanager.com', false)->assertSee('Preferencias de cookies');
    $accepted = $this->post('/cookies/consent', ['decision' => 'accept'])->getCookie(CookieConsent::NAME)->getValue();
    $response = $this->withCookie(CookieConsent::NAME, $accepted)->withCookie('_ga', 'old')->post('/cookies/consent', ['decision' => 'save', 'statistics' => 0])->assertRedirect();
    expect($response->getCookie('_ga', false)->getExpiresTime())->toBeLessThan(time());
    $this->withCookie(CookieConsent::NAME, $response->getCookie(CookieConsent::NAME)->getValue())->get('/')->assertDontSee('googletagmanager.com', false);
});

it('loads no analytics with rejected expired malformed or obsolete consent', function (string $case): void {
    $payload = app(CookieConsent::class)->payload(true);
    if ($case === 'rejected') {
        $payload['statistics'] = false;
    }
    if ($case === 'expired') {
        $payload['decided_at'] = now()->subDays(181)->timestamp;
    }
    if ($case === 'future') {
        $payload['decided_at'] = now()->addDay()->timestamp;
    }
    if ($case === 'obsolete') {
        $payload['version'] = 0;
    }
    if ($case === 'wrong-type') {
        $payload['statistics'] = 'true';
    }
    $cookie = $case === 'malformed' ? '{invalid' : json_encode($payload);
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
})->with(['rejected', 'expired', 'future', 'obsolete', 'wrong-type', 'malformed']);

it('rejects a forged encrypted cookie', function (): void {
    $this->withUnencryptedCookie(CookieConsent::NAME, json_encode(app(CookieConsent::class)->payload(true)))
        ->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
});

it('invalidates consent when analytics settings or the privacy policy change', function (): void {
    $page = LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $cookie = json_encode(app(CookieConsent::class)->payload(true));
    $page->update(['content' => '<p>Nueva política</p>']);
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertDontSee('googletagmanager.com', false);
    $cookie = json_encode(app(CookieConsent::class)->payload(true));
    SearchSetting::current()->update(['measurement_id' => 'G-NEW1234567']);
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertDontSee('googletagmanager.com', false);
});

it('blocks analytics even with valid consent when disabled or the identifier is corrupt', function (array $attributes): void {
    SearchSetting::current()->update($attributes);
    $cookie = json_encode(app(CookieConsent::class)->payload(true));
    $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
})->with([
    [['analytics_enabled' => false]],
    [['cookies_enabled' => false]],
    [['measurement_id' => '\"><script>alert(1)</script>']],
]);

it('validates consent submissions and prevents open redirects', function (array $data): void {
    $this->postJson('/cookies/consent', $data)->assertUnprocessable()->assertCookieMissing(CookieConsent::NAME);
})->with([
    [[]], [['decision' => 'arbitrary']], [['decision' => 'save', 'statistics' => 'yes']],
    [['decision' => 'accept', 'return_to' => '//evil.test']],
    [['decision' => 'accept', 'return_to' => 'https://evil.test']],
    [['decision' => 'accept', 'return_to' => '/admin']],
]);

it('escapes cookie message HTML and includes immediate client revocation before submitting', function (): void {
    SearchSetting::current()->update(['cookie_message' => '<script>alert(1)</script>']);
    $this->get('/')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee("window['ga-disable-' + id] = true", false)->assertSee("window.gtag('consent', 'update'", false)->assertSee('Max-Age=0', false);
});

it('requires CSRF on the consent endpoint', function (): void {
    app()->instance('env', 'local');
    $this->post('/cookies/consent', ['decision' => 'accept'])->assertStatus(419);
});

it('rate limits consent requests', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $this->post('/cookies/consent', ['decision' => 'reject'])->assertRedirect();
    }
    $this->post('/cookies/consent', ['decision' => 'reject'])->assertTooManyRequests();
});

it('initializes analytics only once when the reusable component appears twice', function (): void {
    Route::get('/analytics-preview', fn () => Blade::render('<x-analytics /><x-analytics />'))->middleware('web');
    $cookie = json_encode(app(CookieConsent::class)->payload(true));
    $html = $this->withCookie(CookieConsent::NAME, $cookie)->get('/analytics-preview')->assertOk()->getContent();
    expect(substr_count($html, "window.gtag('config', id)"))->toBe(1);
    expect(substr_count($html, 'https://www.googletagmanager.com/gtag/js'))->toBe(1);
});

it('executes client revocation and removes only analytics cookies before navigation', function (): void {
    $cookie = json_encode(app(CookieConsent::class)->payload(true));
    $html = $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->getContent();
    $process = new Process(['node', base_path('tests/Js/cookie-consent.cjs')]);
    $process->setInput($html)->mustRun();
    expect(trim($process->getOutput()))->toBe('8 client consent scenarios passed');
});
