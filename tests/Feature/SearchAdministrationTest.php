<?php

use App\Filament\Pages\SearchSettings;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SearchSetting;
use App\Models\Service;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets an administrator save all settings with validated Google identifiers', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(SearchSettings::class)->fillForm([
        'default_title' => 'Empresa SEO', 'default_description' => 'Descripción SEO', 'indexable' => true,
        'analytics_enabled' => true, 'measurement_id' => 'G-ABC1234567', 'verification_token' => 'token_123-abc',
        'cookies_enabled' => true, 'cookie_message' => 'Estadísticas opcionales.',
    ])->set('data.id', 42)->call('save')->assertHasNoFormErrors()->assertNotified();
    expect(SearchSetting::current()->default_title)->toBe('Empresa SEO');
    expect(SearchSetting::current()->id)->toBe(1);
    expect(SearchSetting::current()->analytics_enabled)->toBeTrue();
    $this->assertDatabaseCount('search_settings', 1);
});

it('rejects invalid identifiers tokens and overlong text', function (string $field, mixed $value): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(SearchSettings::class)->fillForm([$field => $value])->call('save')->assertHasFormErrors([$field]);
    $this->assertDatabaseCount('search_settings', 0);
})->with([
    ['measurement_id', 'UA-123'], ['measurement_id', 'G-abc123'], ['measurement_id', 'G-123\"><script>'],
    ['verification_token', '<meta name=foo>'], ['verification_token', 'token\" onload=alert(1)'],
    ['default_title', str_repeat('x', 256)], ['default_description', str_repeat('x', 321)],
]);

it('requires a measurement id when enabling analytics', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(SearchSettings::class)->fillForm(['analytics_enabled' => true, 'measurement_id' => null])->call('save')->assertHasFormErrors(['measurement_id']);
});

it('blocks guests editors and inactive administrators on direct critical URLs', function (string $role): void {
    $redirect = Redirect::factory()->create();
    if ($role !== 'guest') {
        $this->actingAs($role === 'editor' ? User::factory()->create() : User::factory()->administrator()->inactive()->create());
    }
    foreach (['/admin/search-settings', '/admin/redirects', '/admin/redirects/create', '/admin/redirects/'.$redirect->id.'/edit'] as $url) {
        $response = $this->get($url);
        if ($role === 'guest') {
            $response->assertRedirect('/admin/login');
        } else {
            $response->assertForbidden();
        }
    }
})->with(['guest', 'editor', 'inactive']);

it('rechecks the settings policy after permissions are revoked', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    $component = Livewire::test(SearchSettings::class)->fillForm(['default_title' => 'Forbidden']);
    $admin->update(['role' => UserRole::Editor]);
    $component->call('save')->assertForbidden();
    $this->assertDatabaseCount('search_settings', 0);
});

it('authorizes all redirect operations only for active administrators', function (): void {
    $record = Redirect::factory()->create();
    $admin = User::factory()->administrator()->create();
    $editor = User::factory()->create();
    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, Redirect::class))->toBeTrue();
        expect(Gate::forUser($editor)->allows($ability, Redirect::class))->toBeFalse();
    }
    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $record))->toBeTrue();
        expect(Gate::forUser($editor)->allows($ability, $record))->toBeFalse();
    }
});

it('creates updates and deletes redirects through Filament', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(CreateRedirect::class)->fillForm(['source' => '/previous', 'destination' => '/', 'status_code' => 302, 'is_active' => true])->call('create')->assertHasNoFormErrors();
    $record = Redirect::query()->sole();
    Livewire::test(EditRedirect::class, ['record' => $record->id])->fillForm(['destination' => '/next', 'status_code' => 301, 'is_active' => false])->call('save')->assertHasNoFormErrors();
    expect($record->fresh()->destination)->toBe('/next');
    expect($record->fresh()->is_active)->toBeFalse();
    Livewire::test(EditRedirect::class, ['record' => $record->id])->callAction('delete')->assertHasNoErrors();
    $this->assertDatabaseCount('redirects', 0);
});

it('persists individual SEO through the authorized post editor', function (): void {
    $this->actingAs(User::factory()->create());
    $post = Post::factory()->create();
    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->fillForm(['seo_title' => 'Título SEO', 'seo_description' => 'Descripción SEO', 'seo_canonical' => '/principal', 'seo_noindex' => true])->call('save')->assertHasNoFormErrors();
    expect($post->fresh()->seo_title)->toBe('Título SEO');
    expect($post->fresh()->seo_noindex)->toBeTrue();
    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->fillForm(['seo_canonical' => '//evil.test'])->call('save')->assertHasFormErrors(['seo_canonical']);
});

it('shows redirect validation errors on the correct Filament fields', function (): void {
    $this->actingAs(User::factory()->administrator()->create());
    Livewire::test(CreateRedirect::class)->fillForm(['source' => '/old', 'destination' => '//evil.test', 'status_code' => 301, 'is_active' => true])->call('create')->assertHasFormErrors(['destination']);
    $this->assertDatabaseCount('redirects', 0);
    $record = Redirect::factory()->create(['source' => '/old', 'destination' => '/new']);
    Livewire::test(EditRedirect::class, ['record' => $record->id])->fillForm(['destination' => '/old'])->call('save')->assertHasFormErrors(['destination']);
    expect($record->fresh()->destination)->toBe('/new');
});

it('creates and edits services without separate SEO fields while preserving legacy data', function (): void {
    $this->actingAs(User::factory()->create());
    $create = Livewire::test(CreateService::class)->assertOk();
    foreach (['seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex'] as $field) {
        $create->assertFormFieldDoesNotExist($field);
    }
    $create->fillForm(['name' => 'Consultoría', 'slug' => 'consultoria', 'summary' => 'Asesoría para empresas.', 'description' => '<p>Descripción.</p>'])
        ->call('create')->assertHasNoFormErrors();
    $service = Service::query()->sole();
    $service->update(['seo_title' => 'Título anterior', 'seo_description' => 'Descripción anterior']);
    $edit = Livewire::test(EditService::class, ['record' => $service->getRouteKey()]);
    foreach (['seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex'] as $field) {
        $edit->assertFormFieldDoesNotExist($field);
    }
    $edit->fillForm(['name' => 'Consultoría actualizada', 'summary' => 'Resumen actualizado.'])->call('save')->assertHasNoFormErrors();
    $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => 'Consultoría actualizada', 'summary' => 'Resumen actualizado.', 'seo_title' => 'Título anterior', 'seo_description' => 'Descripción anterior']);
});
