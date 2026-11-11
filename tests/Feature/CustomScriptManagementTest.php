<?php

namespace Tests\Feature;

use App\Filament\Pages\SearchSettings;
use App\Livewire\CustomScriptManager;
use App\Models\CustomScript;
use App\Models\SearchSetting;
use App\Models\User;
use App\Rules\ScriptMarkup;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomScriptManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function superAdministrator(): User
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        return $user;
    }

    private function scriptData(array $overrides = []): array
    {
        return [...[
            'name' => 'Widget externo', 'position' => 'body_end', 'purpose' => 'preferences',
            'code' => '<script src="https://widgets.example.com/embed.js"></script>', 'is_active' => false,
        ], ...$overrides];
    }

    public function test_superadministrator_can_create_edit_and_delete_with_encryption_and_actor_audit(): void
    {
        $user = $this->superAdministrator();
        $data = $this->scriptData();
        Livewire::test(CustomScriptManager::class)->callAction(TestAction::make('create')->table(), [...$data, 'created_by' => 999, 'updated_by' => 999])
            ->assertHasNoActionErrors()->assertNotified();
        $script = CustomScript::query()->sole();
        $this->assertSame($data['code'], $script->code);
        $this->assertNotSame($data['code'], $script->getRawOriginal('code'));
        $this->assertSame($user->id, $script->created_by);
        $this->assertSame($user->id, $script->updated_by);
        $this->assertNotNull($script->updated_at);
        Livewire::test(CustomScriptManager::class)->callAction(TestAction::make('edit')->table($script), ['name' => 'Widget actualizado', 'is_active' => true])
            ->assertHasNoActionErrors();
        $this->assertSame('Widget actualizado', $script->fresh()->name);
        $this->assertTrue($script->fresh()->is_active);
        Livewire::test(CustomScriptManager::class)->callAction(TestAction::make('delete')->table($script))->assertHasNoActionErrors();
        $this->assertModelMissing($script);
    }

    public function test_script_changes_require_csrf_on_livewire_transport(): void
    {
        $this->superAdministrator();
        app()->instance('env', 'local');
        $this->postJson(route('default-livewire.update'), ['components' => []], ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertDatabaseCount('custom_scripts', 0);
    }

    #[TestWith(['admin'])]
    #[TestWith(['editor'])]
    #[TestWith(['user'])]
    public function test_every_other_role_is_denied_the_manager_and_all_policy_abilities(string $role): void
    {
        $user = User::factory()->create(['role' => UserRole::from($role)]);
        $script = CustomScript::factory()->create();
        $this->actingAs($user);
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, CustomScript::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $script));
        }
        Livewire::test(CustomScriptManager::class)->assertForbidden();
    }

    public function test_revocation_and_unexpected_upload_rpc_are_blocked(): void
    {
        $user = $this->superAdministrator();
        $component = Livewire::test(CustomScriptManager::class)->mountAction(TestAction::make('create')->table())
            ->fillForm($this->scriptData());
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $component->callMountedAction()->assertForbidden();
        $this->assertDatabaseCount('custom_scripts', 0);
        User::query()->whereKey($user->id)->update(['is_active' => true]);
        Livewire::test(CustomScriptManager::class)->call('_startUpload', 'mountedActions.0.data.code', [['name' => 'unexpected.png', 'size' => 10, 'type' => 'image/png']], false)->assertForbidden();
    }

    #[TestWith(['<script src="http://unsecure.example.com/file.js"></script>', 'statistics', 'head'])]
    #[TestWith(['<script src="javascript:alert(1)"></script>', 'statistics', 'head'])]
    #[TestWith(['<img src="https://ads.example.com/pixel" onerror="alert(1)">', 'marketing', 'body_end'])]
    #[TestWith(['<iframe srcdoc="<script>alert(1)</script>"></iframe>', 'preferences', 'body_end'])]
    #[TestWith(['<?php echo "bad"; ?>', 'statistics', 'head'])]
    #[TestWith(['-----BEGIN PRIVATE KEY----- secret', 'statistics', 'head'])]
    #[TestWith(['<script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABCD"></script>', 'statistics', 'head'])]
    #[TestWith(['<script>gtag("config", "G-ABC1234");</script>', 'statistics', 'head'])]
    #[TestWith(['<script>window.noConsent = true;</script>', 'necessary', 'head'])]
    #[TestWith(['<meta name="google-site-verification" content="token">', 'necessary', 'head'])]
    #[TestWith(['<meta name="example-verification" content="token">', 'necessary', 'body_end'])]
    #[TestWith(['window.plainJavaScript = true;', 'statistics', 'head'])]
    public function test_unsafe_duplicated_or_misclassified_markup_is_rejected(string $code, string $purpose, string $position): void
    {
        $this->superAdministrator();
        Livewire::test(CustomScriptManager::class)->callAction(TestAction::make('create')->table(), $this->scriptData(compact('code', 'purpose', 'position')))
            ->assertHasActionErrors(['code']);
        $this->assertDatabaseCount('custom_scripts', 0);
    }

    public function test_size_limits_required_fields_and_duplicate_code_are_validated(): void
    {
        $this->superAdministrator();
        $component = Livewire::test(CustomScriptManager::class)->callAction(TestAction::make('create')->table(), ['name' => null, 'code' => null, 'purpose' => 'bad', 'position' => 'bad'])
            ->assertHasActionErrors(['name', 'code', 'purpose', 'position']);
        $component->unmountAction()->callAction(TestAction::make('create')->table(), $this->scriptData(['code' => '<script>'.str_repeat('x', ScriptMarkup::MAX_BYTES).'</script>']))->assertHasActionErrors(['code']);
        $component->unmountAction()->callAction(TestAction::make('create')->table(), $this->scriptData())->assertHasNoActionErrors();
        $component->unmountAction()->callAction(TestAction::make('create')->table(), $this->scriptData(['name' => 'Duplicado']))->assertHasActionErrors(['code']);
        $this->assertDatabaseCount('custom_scripts', 1);
    }

    public function test_sitemap_status_distinguishes_its_toggle_from_environment_indexing_restrictions(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $settings = SearchSetting::factory()->create(['sitemap_enabled' => true, 'indexable' => true]);
        $component = Livewire::test(SearchSettings::class);
        $status = $component->instance()->crawlStatus('sitemap');
        $this->assertTrue($status['active']);
        $this->assertNull($status['updated']);
        $this->assertStringContainsString('no es público', $status['message']);
        $settings->update(['sitemap_enabled' => false]);
        $status = $component->instance()->crawlStatus('sitemap');
        $this->assertFalse($status['active']);
        $this->assertStringContainsString('desactivado', $status['message']);
    }

    public function test_google_identifiers_toggles_and_sitemap_settings_are_saved_and_validated(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(SearchSettings::class)->fillForm([
            'verification_enabled' => true, 'verification_token' => 'safe-token',
            'tag_manager_enabled' => true, 'tag_manager_id' => 'GTM-ABC1234', 'sitemap_enabled' => false,
        ])->call('save')->assertHasNoFormErrors();
        $settings = SearchSetting::current();
        $this->assertTrue($settings->usesTagManager());
        $this->assertTrue($settings->verification_enabled);
        $this->assertFalse($settings->sitemap_enabled);
        Livewire::test(SearchSettings::class)->fillForm(['tag_manager_id' => 'GTM-abc123'])->call('save')->assertHasFormErrors(['tag_manager_id']);
        Livewire::test(SearchSettings::class)->fillForm(['verification_token' => null])->call('save')->assertHasFormErrors(['verification_token']);
        $this->assertSame('GTM-ABC1234', SearchSetting::current()->tag_manager_id);
    }

    public function test_administrator_never_receives_custom_code_and_superadmin_editor_keeps_it_as_text(): void
    {
        $this->superAdministrator();
        $script = CustomScript::factory()->create(['code' => '<script>window.editorMustNotExecute = true;</script>', 'purpose' => 'statistics']);
        $component = Livewire::test(CustomScriptManager::class)->mountAction(TestAction::make('edit')->table($script))
            ->assertSet('mountedActions.0.data.code', $script->code);
        $instance = $component->instance();
        $html = $instance->getSchema($instance->getMountedActionSchemaName())->toHtml();
        $this->assertStringNotContainsString('<script>window.editorMustNotExecute', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin/search-settings')->assertOk()->assertDontSee('window.editorMustNotExecute', false);
    }
}
