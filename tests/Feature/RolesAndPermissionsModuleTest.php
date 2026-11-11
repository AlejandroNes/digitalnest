<?php

namespace Tests\Feature;

use App\Filament\Pages\RolesAndPermissions;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Permission as PermissionModel;
use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RolesAndPermissionsModuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        RolePermission::clearCache();
    }

    private function signInSuperAdmin(): User
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        return $user;
    }

    private function userManager(): User
    {
        $user = User::factory()->create();
        foreach ([Permission::ViewUsers, Permission::CreateUsers, Permission::UpdateUsers] as $permission) {
            $user->givePermission($permission);
        }
        $this->actingAs($user);

        return $user;
    }

    public function test_empty_profile_stays_empty_and_individual_permissions_are_preserved(): void
    {
        $this->signInSuperAdmin();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $legacy = User::factory()->create(['role' => UserRole::Administrator]);
        $admin->givePermission(Permission::ViewPost);
        Livewire::test(RolesAndPermissions::class)->call('deselectAll')->call('saveAdminPermissions')->assertHasNoErrors()->assertNotified();
        Livewire::test(RolesAndPermissions::class)->assertSet('adminPermissions', [])->assertSet('savedAdminPermissions', []);
        $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::ViewPost));
        $this->assertFalse(RolePermission::allows(UserRole::Administrator, Permission::ViewUsers));
        $this->assertTrue($admin->fresh()->hasPermission(Permission::ViewPost));
        $this->assertFalse($admin->fresh()->hasPermission(Permission::ViewUsers));
        $this->assertFalse($legacy->fresh()->hasPermission(Permission::ViewPost));
        $this->assertDatabaseHas('role_permissions', ['role' => 'admin', 'is_granted' => false]);
    }

    public function test_comparison_uses_saved_permissions_and_has_four_distinct_roles(): void
    {
        $this->signInSuperAdmin();
        $page = Livewire::test(RolesAndPermissions::class)->set('adminPermissions', ['posts.view'])->call('saveAdminPermissions')
            ->call('togglePermission', 'posts.delete', true)->assertHasNoErrors()->assertSee('Sin guardar');
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$page->html());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $table = '//table[caption[normalize-space(.)="Permisos guardados por rol"]]';
        $this->assertCount(5, $xpath->query($table.'/thead/tr/th'));
        $cells = $xpath->query($table.'/tbody/tr[th[normalize-space(.)="Eliminar artículos"]]/td');
        $this->assertSame('Permitido', trim($cells->item(0)->textContent));
        $this->assertSame('Denegado', trim($cells->item(1)->textContent));
        $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::DeletePost));
    }

    public function test_actions_add_required_read_access_and_removing_read_removes_dependents(): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->call('deselectAll')->call('togglePermission', 'posts.create', true)
            ->assertSet('adminPermissions', ['posts.create', 'posts.view'])
            ->call('togglePermission', 'posts.view', false)->assertSet('adminPermissions', [])
            ->call('togglePermission', 'mail.test', true)->assertSet('adminPermissions', ['mail.test', 'settings.view'])
            ->call('toggleModule', 'settings', false)->assertSet('adminPermissions', []);
        $this->assertDatabaseCount('role_permissions', 0);
    }

    public function test_saving_an_action_without_read_access_is_rejected(): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->set('adminPermissions', ['posts.create'])->call('saveAdminPermissions')
            ->assertHasErrors(['adminPermissions']);
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertDatabaseCount('permissions', 0);
    }

    #[TestWith([['posts.view', 'invalid.permission']])]
    #[TestWith([['posts.view', 'posts.view']])]
    #[TestWith([['posts.view', ['users.view']]])]
    #[TestWith([[123]])]
    public function test_invalid_permission_payloads_leave_storage_unchanged(array $permissions): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->set('adminPermissions', $permissions)->call('saveAdminPermissions')->assertHasErrors();
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertDatabaseCount('permissions', 0);
    }

    #[TestWith(['selectAll', []])]
    #[TestWith(['deselectAll', []])]
    #[TestWith(['toggleModule', ['users', true]])]
    #[TestWith(['togglePermission', ['users.create', true]])]
    #[TestWith(['saveAdminPermissions', []])]
    public function test_ordinary_admin_cannot_invoke_configuration_actions(string $method, array $arguments): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(RolesAndPermissions::class)->assertSet('canManageAdminRole', false)->call($method, ...$arguments)->assertForbidden();
        $this->assertDatabaseCount('role_permissions', 0);
    }

    public function test_invalid_module_is_a_validation_error_and_not_a_silent_noop(): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->call('toggleModule', 'nonexistent', true)->assertHasErrors(['adminPermissions']);
    }

    public function test_permissions_changed_in_another_tab_cannot_be_overwritten(): void
    {
        $this->signInSuperAdmin();
        $first = Livewire::test(RolesAndPermissions::class);
        Livewire::test(RolesAndPermissions::class)->set('adminPermissions', ['posts.view'])->call('saveAdminPermissions')->assertHasNoErrors();
        $first->set('adminPermissions', ['services.view'])->call('saveAdminPermissions')->assertHasErrors(['adminPermissions']);
        $this->assertTrue(RolePermission::allows(UserRole::Admin, Permission::ViewPost));
        $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::ViewService));
        $first->call('resetAdminPermissions')->assertSet('adminPermissions', ['posts.view'])->assertHasNoErrors();
    }

    public function test_revocation_after_opening_page_blocks_save(): void
    {
        $actor = $this->signInSuperAdmin();
        $page = Livewire::test(RolesAndPermissions::class)->set('adminPermissions', ['posts.view']);
        DB::table('users')->where('id', $actor->id)->update(['is_active' => false]);
        $page->call('saveAdminPermissions')->assertForbidden();
        $this->assertDatabaseCount('role_permissions', 0);
    }

    #[TestWith([[]])]
    #[TestWith([['posts.view']])]
    public function test_seeder_does_not_restore_revoked_admin_permissions(array $permissions): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->set('adminPermissions', $permissions)->call('saveAdminPermissions')->assertHasNoErrors();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertSame($permissions, RolePermission::permissionsFor(UserRole::Admin));
        $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::DeletePost));
    }

    public function test_legacy_admin_assignments_are_used_and_admin_filter_includes_both_aliases(): void
    {
        $super = $this->signInSuperAdmin();
        $permission = PermissionModel::query()->create(['name' => 'posts.view', 'label' => 'Ver artículos', 'module' => 'posts', 'action' => 'view']);
        DB::table('role_permissions')->insert(['role' => 'administrator', 'permission_id' => $permission->id]);
        RolePermission::clearCache();
        $this->assertSame(['posts.view'], RolePermission::permissionsFor(UserRole::Admin));
        $this->assertFalse(RolePermission::allows(UserRole::Administrator, Permission::DeletePost));
        $canonical = User::factory()->create(['role' => UserRole::Admin]);
        $legacy = User::factory()->create(['role' => UserRole::Administrator]);
        $editor = User::factory()->create();
        Livewire::test(ListUsers::class)->filterTable('role', 'admin')->assertCanSeeTableRecords([$canonical, $legacy])->assertCanNotSeeTableRecords([$editor, $super]);
    }

    public function test_permission_cache_is_refreshed_for_a_new_request(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $request = request();
        try {
            app()->instance('request', Request::create('/first'));
            $this->assertTrue(RolePermission::allows(UserRole::Admin, Permission::ViewUsers));
            DB::table('role_permissions')->where('role', 'admin')->update(['is_granted' => false]);
            app()->instance('request', Request::create('/second'));
            $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::ViewUsers));
        } finally {
            app()->instance('request', $request);
            RolePermission::clearCache();
        }
    }

    public function test_permission_storage_failure_does_not_grant_default_admin_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Schema::rename('permissions', 'unavailable_permissions');
        RolePermission::clearCache();
        try {
            $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::ViewUsers));
        } finally {
            Schema::rename('unavailable_permissions', 'permissions');
            RolePermission::clearCache();
        }
    }

    public function test_installation_with_pending_migration_can_read_but_cannot_save_permissions(): void
    {
        $this->signInSuperAdmin();
        $migration = require database_path('migrations/2026_10_13_221244_add_is_granted_to_role_permissions_table.php');
        $migration->down();
        RolePermission::clearCache();
        try {
            Livewire::test(RolesAndPermissions::class)->assertSee('La actualización del módulo está pendiente.')
                ->set('adminPermissions', [])->call('saveAdminPermissions')->assertHasErrors(['adminPermissions']);
            $this->assertDatabaseCount('role_permissions', 0);
        } finally {
            $migration->up();
            RolePermission::clearCache();
        }
    }

    public function test_rollback_cannot_turn_explicit_denials_into_grants(): void
    {
        $this->signInSuperAdmin();
        Livewire::test(RolesAndPermissions::class)->call('deselectAll')->call('saveAdminPermissions');
        $migration = require database_path('migrations/2026_10_13_221244_add_is_granted_to_role_permissions_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must not restore denied permissions.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('permisos denegados explícitamente', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('role_permissions', 'is_granted'));
        $this->assertFalse(RolePermission::allows(UserRole::Admin, Permission::ViewUsers));
    }

    public function test_user_manager_cannot_assign_a_role_with_permissions_they_lack(): void
    {
        $actor = $this->userManager();
        $target = User::factory()->create(['name' => 'Nombre original']);
        $this->assertFalse($actor->can('assignRole', [$target, UserRole::Admin]));
        Livewire::test(EditUser::class, ['record' => $target->id])->fillForm(['name' => 'Cambio rechazado', 'role' => 'admin'])
            ->call('save')->assertHasFormErrors(['role']);
        $this->assertSame('Nombre original', $target->fresh()->name);
        $this->assertSame(UserRole::Editor, $target->fresh()->role);
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Nueva cuenta', 'email' => 'new-admin@example.test', 'password' => 'Strong-Password123!',
            'password_confirmation' => 'Strong-Password123!', 'role' => 'admin', 'is_active' => true,
        ])->call('create')->assertHasFormErrors(['role']);
        $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.test']);
    }

    public function test_invalid_direct_permission_cannot_partially_change_user_data(): void
    {
        $this->userManager();
        $target = User::factory()->create(['name' => 'Sin cambiar']);
        Livewire::test(EditUser::class, ['record' => $target->id])->fillForm(['name' => 'Cambio rechazado', 'custom_permissions' => ['posts.delete']])
            ->call('save')->assertHasFormErrors();
        $this->assertSame('Sin cambiar', $target->fresh()->name);
        $this->assertFalse($target->fresh()->hasPermission(Permission::DeletePost));
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $target->id]);
    }

    public function test_existing_direct_permission_can_be_preserved_when_actor_cannot_grant_it(): void
    {
        $actor = $this->userManager();
        $target = User::factory()->create();
        $target->givePermission(Permission::ViewSettings);
        $this->assertFalse($actor->hasPermission(Permission::ViewSettings));
        Livewire::test(EditUser::class, ['record' => $target->id])->fillForm(['name' => 'Nombre actualizado'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($target->fresh()->hasPermission(Permission::ViewSettings));
        $this->assertSame('Nombre actualizado', $target->fresh()->name);
    }

    public function test_hidden_client_permission_field_does_not_process_forged_state(): void
    {
        $this->signInSuperAdmin();
        $target = User::factory()->create(['role' => UserRole::User]);
        Livewire::test(EditUser::class, ['record' => $target->id])->set('data.custom_permissions', ['settings.view'])
            ->fillForm(['name' => 'Cliente actualizado'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Cliente actualizado', $target->fresh()->name);
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $target->id]);
    }

    public function test_permission_changes_refresh_previously_loaded_relation(): void
    {
        $user = User::factory()->create();
        $user->load('permissions');
        $user->givePermission(Permission::ViewSettings);
        $this->assertTrue($user->hasPermission(Permission::ViewSettings));
        $user->load('permissions');
        $user->revokePermission(Permission::ViewSettings);
        $this->assertFalse($user->hasPermission(Permission::ViewSettings));
    }

    public function test_empty_permission_sync_cannot_bypass_self_protection(): void
    {
        $actor = $this->userManager();
        $this->expectException(AuthorizationException::class);
        $actor->syncPermissions([], $actor);
    }

    public function test_unknown_permission_is_denied_even_for_superadministrator(): void
    {
        $super = $this->signInSuperAdmin();
        $this->assertFalse($super->hasPermission('unknown.permission'));
        $this->assertTrue($super->hasPermission(Permission::ViewUsers));
    }
}
