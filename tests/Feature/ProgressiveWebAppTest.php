<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProgressiveWebAppTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manifest_is_installable_and_icons_exist(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertStringStartsWith('/', $manifest['start_url']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ([...$manifest['icons'], ...array_merge(...array_column($manifest['shortcuts'], 'icons'))] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        [$width, $height] = getimagesize(public_path('icons/icon-512.png'));
        $this->assertSame([512, 512], [$width, $height]);
    }

    public function test_layout_links_manifest_and_ios_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('data-install-prompt', false);
    }

    public function test_offline_page_is_available_and_not_indexed(): void
    {
        $this->get('/offline')->assertOk()->assertSee('Estás sin conexión')->assertSee('noindex', false);
    }

    public function test_service_worker_never_handles_private_or_outbound_routes(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        foreach (['/^\/admin/', '/^\/go\//', '/^\/livewire/', '/^\/cookies/'] as $pattern) {
            $this->assertStringContainsString($pattern, $worker);
        }
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("const OFFLINE_URL = '/offline'", $worker);
    }
}
