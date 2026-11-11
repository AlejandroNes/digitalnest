<?php

use App\Filament\Pages\GeneralSettings;
use App\Models\GeneralSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('reads cached site identity with Laravel thirteen secure database serialization', function (): void {
    config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
    $setting = GeneralSetting::factory()->create(['id' => 1, 'company_name' => 'Identidad persistente', 'social_links' => [['label' => 'Ejemplo', 'url' => 'https://example.test']]]);
    expect(GeneralSetting::current()->company_name)->toBe('Identidad persistente');
    $cached = Cache::get('general_settings');
    expect($cached)->toBeArray();
    expect(GeneralSetting::current()->exists)->toBeTrue();
    expect(GeneralSetting::current()->social_links)->toBe([['label' => 'Ejemplo', 'url' => 'https://example.test']]);
    $this->get('/contacto')->assertOk();
    $this->assertDatabaseCount('general_settings', 1);
});

it('recovers old object caches while preserving unsaved defaults and singleton creation', function (): void {
    config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
    Cache::put('general_settings', new GeneralSetting, 60);
    expect(GeneralSetting::current()->exists)->toBeFalse();
    expect(Cache::get('general_settings'))->toBeArray();
    $this->actingAs(User::factory()->administrator()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(GeneralSettings::class)->fillForm(['company_name' => 'Nombre actualizado'])->call('save')->assertHasNoFormErrors();
    expect(GeneralSetting::current()->exists)->toBeTrue();
    expect(GeneralSetting::current()->company_name)->toBe('Nombre actualizado');
    $this->assertDatabaseCount('general_settings', 1);
});
