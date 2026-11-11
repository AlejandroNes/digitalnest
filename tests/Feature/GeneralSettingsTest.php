<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralSettings;
use App\Http\Middleware\ApplySiteSettings;
use App\Livewire\MediaLibraryBrowser;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\MediaUploader;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_settings_blade_helper_escapes_stored_text(): void
    {
        GeneralSetting::factory()->create(['id' => 1, 'company_name' => '<script>alert(1)</script>']);
        $html = Blade::render("@settings('company_name')");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_defaults_can_be_read_without_creating_a_record(): void
    {
        $settings = GeneralSetting::current();
        $this->assertSame('Mi empresa', $settings->company_name);
        $this->assertSame('es', $settings->locale);
        $this->assertSame('UTC', $settings->timezone);
        $this->assertFalse($settings->maintenance_enabled);
        $this->assertNull($settings->imageUrl('logo'));
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_public_requests_use_saved_locale_and_timezone_without_leaking_to_other_requests(): void
    {
        GeneralSetting::factory()->create(['id' => 1, 'locale' => 'es', 'timezone' => 'America/La_Paz']);
        $originalLocale = app()->getLocale();
        $originalTimezone = date_default_timezone_get();
        Route::get('/settings-preview', fn () => response()->json([
            'locale' => app()->getLocale(),
            'timezone' => now()->timezoneName,
        ]))->middleware(ApplySiteSettings::class);

        $this->get('/settings-preview')->assertOk()->assertExactJson([
            'locale' => 'es', 'timezone' => 'America/La_Paz',
        ]);
        $this->assertSame($originalLocale, app()->getLocale());
        $this->assertSame($originalTimezone, date_default_timezone_get());
    }

    public function test_admin_can_save_and_read_general_settings_and_replace_singleton(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(GeneralSettings::class)->fillForm([
            'company_name' => 'Empresa local', 'description' => 'Servicios corporativos',
            'email' => 'contact@example.test', 'phone' => '+591 22222222', 'whatsapp' => '+59177777777',
            'address' => 'La Paz', 'social_links' => [['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/example']],
            'locale' => 'es', 'timezone' => 'America/La_Paz', 'maintenance_enabled' => false,
        ])->set('data.id', 42)->call('save')->assertHasNoFormErrors()->assertNotified();

        $settings = GeneralSetting::current();
        $this->assertSame('Empresa local', $settings->company_name);
        $this->assertSame('America/La_Paz', $settings->timezone);
        $this->assertSame('+59177777777', $settings->whatsapp);
        $this->assertSame('https://www.linkedin.com/company/example', $settings->social_links[0]['url']);
        $this->assertSame(1, $settings->id);

        Livewire::test(GeneralSettings::class)->fillForm(['company_name' => 'Nombre actualizado'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Nombre actualizado', GeneralSetting::current()->company_name);
        $this->assertDatabaseCount('general_settings', 1);
    }

    #[TestWith(['company_name', '', 'required'])]
    #[TestWith(['company_name', 'overlong', 'max'])]
    #[TestWith(['email', 'invalid-email', 'email'])]
    #[TestWith(['locale', 'arbitrary', 'in'])]
    #[TestWith(['timezone', 'Invalid/Timezone', 'timezone'])]
    #[TestWith(['whatsapp', 'javascript:alert(1)', 'regex'])]
    public function test_invalid_settings_are_not_persisted(string $field, string $value, string $rule): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $value = $value === 'overlong' ? str_repeat('a', 256) : $value;
        Livewire::test(GeneralSettings::class)->fillForm([$field => $value])->call('save')
            ->assertHasFormErrors([$field => $rule]);
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_social_links_reject_active_schemes(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(GeneralSettings::class)->fillForm([
            'social_links' => [['label' => 'Enlace', 'url' => 'javascript:alert(1)']],
        ])->call('save')->assertHasFormErrors();
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_settings_save_rechecks_policy_after_role_revocation(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $component = Livewire::test(GeneralSettings::class)->fillForm(['company_name' => 'Forbidden']);
        $admin->update(['role' => UserRole::Editor]);
        $component->call('save')->assertForbidden();
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_editor_cannot_mount_settings_component(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(GeneralSettings::class)->assertForbidden();
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_existing_library_image_is_reused_and_read_from_frontend(): void
    {
        Storage::fake('public');
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);
        $media = app(MediaUploader::class)->upload($user, UploadedFile::fake()->image('logo.png', 100, 100), 'Logo', 'Empresa')['asset']->getFirstMedia('images');
        Livewire::test(GeneralSettings::class)->fillForm(['logo_media_id' => $media->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($media->getUrl(), GeneralSetting::current()->imageUrl('logo'));
        $this->assertNull(GeneralSetting::current()->imageUrl('email'));
        Livewire::test(GeneralSettings::class)->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('media', 1);
    }

    #[TestWith(['payload.svg', 'image/svg+xml'])]
    #[TestWith(['payload.png', 'text/plain'])]
    public function test_non_image_or_active_uploads_are_rejected(string $name, string $mime): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'parent:data.logo_media_id', 'context' => 'logo'])
            ->callAction(TestAction::make('uploadImages')->table(), ['images' => [UploadedFile::fake()->create($name, 10, $mime)]])->assertHasActionErrors(['images']);
        $this->assertDatabaseCount('general_settings', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversized_images_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'parent:data.logo_media_id', 'context' => 'logo'])
            ->callAction(TestAction::make('uploadImages')->table(), ['images' => [UploadedFile::fake()->image('large.png')->size(2049)]])->assertHasActionErrors(['images']);
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_tampered_existing_file_path_is_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('unrelated.png', 'unrelated file');
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(GeneralSettings::class)->fillForm(['logo_media_id' => 'unrelated.png'])
            ->call('save')->assertHasFormErrors(['logo_media_id']);
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_maintenance_blocks_public_site_and_can_be_disabled_from_panel(): void
    {
        GeneralSetting::factory()->create(['id' => 1, 'maintenance_enabled' => true]);
        $this->get('/')->assertStatus(503)->assertHeader('Retry-After', '3600');
        $this->get('/admin/login')->assertOk();
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin')->assertOk();
        Livewire::test(GeneralSettings::class)->fillForm(['maintenance_enabled' => false])->call('save')->assertHasNoFormErrors();
        $this->get('/')->assertOk();
    }
}
