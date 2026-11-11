<?php

use App\Filament\Pages\GeneralSettings;
use App\Models\ContactMessage;
use App\Models\GeneralSetting;
use App\Models\LegalPage;
use App\Models\MailSetting;
use App\Models\Project;
use App\Models\SearchSetting;
use App\Models\User;
use App\Services\ApplicationCache;
use App\Services\CookieConsent;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('invalidates public settings on model creation update and deletion with standard stores', function (string $store): void {
    config(['cache.default' => $store, 'cache.serializable_classes' => false]);
    expect(GeneralSetting::current(false)->company_name)->toBe('Mi empresa');
    $general = GeneralSetting::factory()->create(['id' => 1, 'company_name' => 'Inicial']);
    expect(GeneralSetting::current(false)->company_name)->toBe('Inicial');
    $general->update(['company_name' => 'Actualizada']);
    expect(GeneralSetting::current(false)->company_name)->toBe('Actualizada');
    $general->delete();
    expect(GeneralSetting::current(false)->exists)->toBeFalse();
    expect(SearchSetting::current()->indexable)->toBeFalse();
    $search = SearchSetting::factory()->create(['id' => 1, 'indexable' => true]);
    expect(SearchSetting::current()->indexable)->toBeTrue();
    $search->update(['indexable' => false]);
    expect(SearchSetting::current()->indexable)->toBeFalse();
    $search->delete();
    expect(SearchSetting::current()->exists)->toBeFalse();
})->with(['array', 'database']);

it('does not persist contact details messages or SMTP credentials in its cache', function (): void {
    config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
    GeneralSetting::factory()->create(['id' => 1, 'email' => 'private@example.test', 'phone' => '+59177777777', 'address' => 'Dirección privada', 'social_links' => []]);
    ContactMessage::factory()->create(['message' => 'Contenido privado de consulta']);
    MailSetting::factory()->create(['smtp_username' => 'private-smtp-user', 'smtp_password' => 'private-smtp-password']);
    GeneralSetting::current(false);
    SearchSetting::current();
    app(CookieConsent::class)->policyRevision();
    MailSetting::current();
    $cached = json_encode(DB::table('cache')->pluck('value')->all());
    foreach (['private@example.test', '+59177777777', 'Dirección privada', 'Contenido privado', 'private-smtp-user', 'private-smtp-password'] as $private) {
        expect($cached)->not->toContain($private);
    }
    expect(Cache::get(ApplicationCache::GENERAL))->not->toHaveKeys(['email', 'phone', 'whatsapp', 'address', 'social_links']);
    expect(GeneralSetting::current()->email)->toBe('private@example.test');
});

it('reuses public configuration and privacy queries without sharing visitor consent', function (): void {
    SearchSetting::factory()->create(['id' => 1, 'analytics_enabled' => true, 'measurement_id' => 'G-TEST12345']);
    $privacy = LegalPage::factory()->create(['type' => 'privacy', 'is_published' => true]);
    $consent = app(CookieConsent::class);
    DB::enableQueryLog();
    for ($i = 0; $i < 4; $i++) {
        SearchSetting::current();
        $consent->policyRevision();
    }
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'search_settings') || str_contains($query['query'], 'legal_pages'));
    DB::disableQueryLog();
    expect($queries)->toHaveCount(2);
    $accepted = Request::create('/');
    $accepted->cookies->set(CookieConsent::NAME, json_encode($consent->payload(true)));
    expect($consent->allowsStatistics($accepted))->toBeTrue();
    expect($consent->allowsStatistics(Request::create('/')))->toBeFalse();
    $privacy->update(['content' => '<p>Política nueva</p>']);
    expect($consent->allowsStatistics($accepted))->toBeFalse();
    $privacy->update(['is_published' => false]);
    expect($consent->policyRevision())->toBe(0);
    $privacy->delete();
    expect($consent->policyRevision())->toBe(0);
});

it('clears only owned keys through the authorized panel action', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    foreach ([ApplicationCache::GENERAL, ApplicationCache::SEARCH, ApplicationCache::PRIVACY_REVISION] as $key) {
        Cache::put($key, [], 300);
    }
    Cache::put('unrelated.session', 'preservada', 300);
    Cache::put('unrelated.rate_limit', 3, 300);
    Livewire::test(GeneralSettings::class)->callAction('clearApplicationCache')->assertNotified();
    expect(Cache::has(ApplicationCache::SEARCH))->toBeFalse();
    expect(Cache::has(ApplicationCache::PRIVACY_REVISION))->toBeFalse();
    expect(Cache::get('unrelated.session'))->toBe('preservada');
    expect(Cache::get('unrelated.rate_limit'))->toBe(3);
});

it('rejects direct settings URLs and cache actions after permission revocation', function (): void {
    $editor = User::factory()->create();
    $this->actingAs($editor)->get('/admin/general-settings')->assertForbidden();
    Livewire::test(GeneralSettings::class)->assertForbidden();
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $component = Livewire::test(GeneralSettings::class);
    Cache::put(ApplicationCache::SEARCH, ['retained' => true], 300);
    $admin->update(['role' => UserRole::Editor]);
    $component->call('clearApplicationCache')->assertForbidden();
    expect(Cache::get(ApplicationCache::SEARCH))->toBe(['retained' => true]);
});

it('reads featured projects across requests with database cache without deserializing models', function (): void {
    $project = Project::factory()->published()->featured()->create();
    config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
    expect(Project::featuredPublished(3)->modelKeys())->toBe([$project->id]);

    $this->app->forgetInstance('cache.__memoized:database');
    $projects = Project::featuredPublished(3);

    expect($projects->modelKeys())->toBe([$project->id]);
    expect($projects->first()->relationLoaded('category'))->toBeTrue();
    expect($projects->first()->relationLoaded('image'))->toBeTrue();
});

it('invalidates arbitrary featured list sizes on publication changes and deletion', function (string $store): void {
    $project = Project::factory()->published()->featured()->create();
    config(['cache.default' => $store]);
    expect(Project::featuredPublished(7)->modelKeys())->toBe([$project->id]);
    expect(Project::featuredPublished(11)->modelKeys())->toBe([$project->id]);

    $project->update(['status' => 'draft']);
    expect(Project::featuredPublished(7))->toBeEmpty();
    expect(Project::featuredPublished(11))->toBeEmpty();
    $project->update(['status' => 'published']);
    expect(Project::featuredPublished(7)->modelKeys())->toBe([$project->id]);
    $project->delete();
    expect(Project::featuredPublished(7))->toBeEmpty();
})->with(['array', 'database']);

it('refreshes featured lists after the application cache is cleared', function (string $store): void {
    $first = Project::factory()->published()->featured()->create(['sort_order' => 1]);
    $second = Project::factory()->published()->featured()->create(['sort_order' => 2]);
    config(['cache.default' => $store]);
    expect(Project::featuredPublished(1)->modelKeys())->toBe([$first->id]);
    Project::query()->whereKey($second->id)->update(['sort_order' => 0]);

    ApplicationCache::clear();

    expect(Project::featuredPublished(1)->modelKeys())->toBe([$second->id]);
})->with(['array', 'database']);

it('rechecks publication even when a bulk update bypasses cache invalidation', function (): void {
    $project = Project::factory()->published()->featured()->create();
    expect(Project::featuredPublished(7)->modelKeys())->toBe([$project->id]);

    Project::query()->whereKey($project->id)->update(['status' => 'draft']);

    expect(Project::featuredPublished(7))->toBeEmpty();
});
