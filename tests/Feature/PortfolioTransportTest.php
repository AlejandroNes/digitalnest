<?php

namespace Tests\Feature;

use App\Models\GeneralSetting;
use App\Models\Project;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortfolioTransportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ajax_filters_follow_site_settings_and_stop_during_maintenance(): void
    {
        GeneralSetting::factory()->create(['id' => 1, 'maintenance_enabled' => false]);
        $project = Project::factory()->published()->create(['title' => 'Trabajo del portafolio']);
        $html = $this->get('/proyectos')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = ['components' => [[
            'snapshot' => $snapshot, 'updates' => (object) [],
            'calls' => [['method' => 'openProject', 'params' => [$project->id]]],
        ]]];
        $this->postJson(route('default-livewire.update'), $payload, ['X-Livewire' => 'true'])->assertOk()
            ->assertJsonPath('components.0.effects.returns.0', null);
        Livewire::flushState();
        GeneralSetting::current(false)->update(['maintenance_enabled' => true]);
        $this->postJson(route('default-livewire.update'), $payload, ['X-Livewire' => 'true'])->assertStatus(503)
            ->assertSee('El sitio está en mantenimiento');
    }
}
