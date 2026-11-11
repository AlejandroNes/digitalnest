<?php

namespace Tests\Feature;

use App\Models\CustomScript;
use App\Models\SearchSetting;
use App\Services\ApplicationCache;
use App\Services\CookieConsent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SeoIntegrationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_custom_scripts_work_with_secure_database_cache_and_keep_cached_code_encrypted(): void
    {
        config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
        SearchSetting::factory()->create();
        $script = CustomScript::factory()->create(['is_active' => true, 'code' => '<meta name="example-verification" content="database-cache">']);
        $this->get('/')->assertOk()->assertSee('content="database-cache"', false);
        $cached = Cache::get(ApplicationCache::SCRIPTS);
        $this->assertIsArray($cached);
        $this->assertIsArray($cached[0]);
        $this->assertSame($script->getRawOriginal('code'), $cached[0]['code']);
        $this->assertNotSame($script->code, $cached[0]['code']);
        $this->get('/contacto')->assertOk()->assertSee('content="database-cache"', false);
    }

    public function test_sitemap_copy_button_reports_success_and_browser_permission_failure(): void
    {
        $html = view('filament.pages.seo-crawl-status', [
            'kind' => 'sitemap', 'url' => 'https://corporate.example.com/sitemap.xml', 'active' => true,
            'message' => 'Activo', 'updated' => null, 'sitemapUrl' => null,
        ])->render();
        preg_match('/x-data="([^"]+)"/', $html, $data);
        preg_match('/x-on:click="([^"]+)"/', $html, $handler);
        $this->assertArrayHasKey(1, $data);
        $this->assertArrayHasKey(1, $handler);
        $process = new Process(['node', '-e', <<<'JS'
const assert = require('node:assert/strict');
const vm = require('node:vm');
const input = JSON.parse(require('node:fs').readFileSync(0, 'utf8'));
(async () => {
    for (const fails of [false, true]) {
        const sandbox = {navigator: {clipboard: {writeText: async url => {
            assert.equal(url, 'https://corporate.example.com/sitemap.xml');
            if (fails) throw new Error('Permission denied');
        }}}};
        Object.assign(sandbox, vm.runInNewContext('(' + input.data + ')', sandbox));
        await vm.runInNewContext(input.handler, sandbox);
        assert.equal(sandbox.copied, !fails);
        assert.equal(sandbox.copyError, fails);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
JS]);
        $process->setInput(json_encode(['data' => html_entity_decode($data[1], ENT_QUOTES, 'UTF-8'), 'handler' => html_entity_decode($handler[1], ENT_QUOTES, 'UTF-8')]));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_verification_can_be_disabled_without_losing_the_token(): void
    {
        $settings = SearchSetting::factory()->create(['verification_token' => 'Safe-token_123', 'verification_enabled' => true]);
        $this->get('/')->assertOk()->assertSee('name="google-site-verification" content="Safe-token_123"', false);
        $settings->update(['verification_enabled' => false]);
        $this->get('/')->assertDontSee('google-site-verification', false);
        $this->assertSame('Safe-token_123', $settings->fresh()->verification_token);
    }

    public function test_tag_manager_requires_consent_appears_at_official_positions_and_suppresses_direct_ga(): void
    {
        SearchSetting::factory()->create(['tag_manager_enabled' => true, 'tag_manager_id' => 'GTM-ABC1234', 'analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567']);
        $this->get('/')->assertDontSee('googletagmanager.com', false);
        $cookie = json_encode(app(CookieConsent::class)->payload(true));
        $html = $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/gtm.js?id=', $html);
        $this->assertStringNotContainsString('/gtag/js', $html);
        $headEnd = strpos($html, '</head>');
        $body = strpos($html, '<body');
        $this->assertLessThan($headEnd, strpos($html, '/gtm.js?id='));
        $this->assertGreaterThan($body, strpos($html, '<noscript><iframe'));
        $this->assertLessThan(strpos($html, '<main'), strpos($html, '<noscript><iframe'));
        $this->assertSame(1, substr_count($html, '/gtm.js?id='));
        $this->assertSame(1, substr_count($html, '/ns.html?id=GTM-ABC1234'));
    }

    public function test_disabling_tag_manager_restores_direct_ga_after_fresh_consent(): void
    {
        $settings = SearchSetting::factory()->create(['tag_manager_enabled' => true, 'tag_manager_id' => 'GTM-ABC1234', 'analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567']);
        $old = json_encode(app(CookieConsent::class)->payload(true));
        $settings->update(['tag_manager_enabled' => false]);
        $this->withCookie(CookieConsent::NAME, $old)->get('/')->assertDontSee('googletagmanager.com', false);
        $cookie = json_encode(app(CookieConsent::class)->payload(true));
        $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertSee('/gtag/js', false)->assertDontSee('/gtm.js', false);
    }

    public function test_custom_fragments_follow_independent_purposes_positions_and_activation(): void
    {
        SearchSetting::factory()->create();
        CustomScript::factory()->create(['name' => 'Verification', 'is_active' => true, 'code' => '<meta name="example-verification" content="verified">']);
        CustomScript::factory()->create(['is_active' => true, 'position' => 'body_start', 'purpose' => 'statistics', 'code' => '<script>window.statisticsWidget = true;</script>']);
        CustomScript::factory()->create(['is_active' => true, 'position' => 'body_end', 'purpose' => 'marketing', 'code' => '<img src="https://ads.example.com/pixel" alt="">']);
        CustomScript::factory()->create(['is_active' => true, 'position' => 'body_end', 'purpose' => 'preferences', 'code' => '<script>window.optionalWidget = true;</script>']);
        CustomScript::factory()->create(['is_active' => false, 'position' => 'head', 'purpose' => 'statistics', 'code' => '<script>window.inactiveWidget = true;</script>']);
        $this->get('/')->assertSee('name="example-verification"', false)->assertDontSee('window.statisticsWidget', false)->assertDontSee('ads.example.com', false)->assertDontSee('window.optionalWidget', false);
        $cookie = json_encode(app(CookieConsent::class)->payload(true, false, true));
        $html = $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('window.statisticsWidget', $html);
        $this->assertStringContainsString('window.optionalWidget', $html);
        $this->assertStringNotContainsString('ads.example.com', $html);
        $this->assertStringNotContainsString('window.inactiveWidget', $html);
        $this->assertLessThan(strpos($html, '<main'), strpos($html, 'window.statisticsWidget'));
        $this->assertGreaterThan(strpos($html, '</main>'), strpos($html, 'window.optionalWidget'));
    }

    public function test_changing_active_tracking_code_or_deleting_it_invalidates_consent_and_cached_scripts(): void
    {
        SearchSetting::factory()->create();
        $script = CustomScript::factory()->create(['is_active' => true, 'purpose' => 'statistics', 'code' => '<script>window.initialCode = true;</script>']);
        $cookie = json_encode(app(CookieConsent::class)->payload(true));
        $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertSee('window.initialCode', false);
        $script->update(['code' => '<script>window.changedCode = true;</script>']);
        $this->withCookie(CookieConsent::NAME, $cookie)->get('/')->assertDontSee('window.changedCode', false)->assertDontSee('window.initialCode', false);
        $fresh = json_encode(app(CookieConsent::class)->payload(true));
        $this->withCookie(CookieConsent::NAME, $fresh)->get('/')->assertSee('window.changedCode', false);
        $script->delete();
        $this->withCookie(CookieConsent::NAME, $fresh)->get('/')->assertDontSee('window.changedCode', false);
    }

    public function test_legacy_statistics_consent_does_not_enable_new_advertising_or_widget_purposes(): void
    {
        SearchSetting::factory()->create();
        CustomScript::factory()->create(['is_active' => true, 'purpose' => 'marketing', 'code' => '<script>window.advertisingCode = true;</script>']);
        $payload = app(CookieConsent::class)->payload(true);
        unset($payload['marketing'], $payload['preferences']);
        $this->withCookie(CookieConsent::NAME, json_encode($payload))->get('/')->assertDontSee('window.advertisingCode', false);
        $response = $this->post('/cookies/consent', ['decision' => 'save', 'statistics' => 1, 'marketing' => 0, 'preferences' => 1])->assertRedirect();
        $saved = json_decode($response->getCookie(CookieConsent::NAME)->getValue(), true);
        $this->assertTrue($saved['statistics']);
        $this->assertFalse($saved['marketing']);
        $this->assertTrue($saved['preferences']);
    }

    public function test_duplicate_components_and_external_script_sources_are_not_loaded_twice(): void
    {
        SearchSetting::factory()->create();
        CustomScript::factory()->create(['is_active' => true, 'purpose' => 'preferences', 'code' => '<script src="https://widgets.example.com/embed.js"></script>']);
        CustomScript::factory()->create(['is_active' => true, 'purpose' => 'preferences', 'position' => 'body_end', 'code' => '<script async src="https://widgets.example.com/embed.js"></script>']);
        Route::get('/integration-preview', fn () => Blade::render('<x-site-integrations /><x-site-integrations /><x-site-integrations position="body_end" />'))->middleware('web');
        $cookie = json_encode(app(CookieConsent::class)->payload(false, false, true));
        $html = $this->withCookie(CookieConsent::NAME, $cookie)->get('/integration-preview')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'https://widgets.example.com/embed.js'));
    }

    public function test_csp_is_preserved_and_trusted_request_nonce_is_applied_to_custom_and_google_scripts(): void
    {
        SearchSetting::factory()->create(['analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567']);
        CustomScript::factory()->create(['is_active' => true, 'purpose' => 'statistics', 'code' => '<script>window.nonceProtected = true;</script>']);
        Route::get('/integration-csp', function (Request $request) {
            $request->attributes->set('csp_nonce', 'trusted-nonce');

            return response(Blade::render('<x-site-integrations />'))->header('Content-Security-Policy', "script-src 'nonce-trusted-nonce' https://www.googletagmanager.com");
        })->middleware('web');
        $cookie = json_encode(app(CookieConsent::class)->payload(true));
        $this->withCookie(CookieConsent::NAME, $cookie)->get('/integration-csp')->assertOk()
            ->assertHeader('Content-Security-Policy', "script-src 'nonce-trusted-nonce' https://www.googletagmanager.com")
            ->assertSee('<script nonce="trusted-nonce">window.nonceProtected', false)
            ->assertDontSee('unsafe-inline', false);
    }

    public function test_integration_components_refuse_to_render_inside_administration_or_api_routes(): void
    {
        SearchSetting::factory()->create(['analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567']);
        CustomScript::factory()->create(['is_active' => true]);
        Route::get('/admin/integration-preview', fn () => Blade::render('<x-site-integrations />'));
        $cookie = json_encode(app(CookieConsent::class)->payload(true));
        $this->withCookie(CookieConsent::NAME, $cookie)->get('/admin/integration-preview')->assertOk()
            ->assertDontSee('example-verification', false)->assertDontSee('googletagmanager.com', false);
    }
}
