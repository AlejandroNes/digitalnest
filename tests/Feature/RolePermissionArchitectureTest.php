<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Post;
use App\Models\User;
use App\Permission;
use App\Policies\UserPolicy;
use App\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('enforces that only the designated email can be super_admin and ensures uniqueness', function (): void {
    // Cannot create super_admin with non-designated email
    expect(fn () => User::factory()->create([
        'email' => 'intruder@example.com',
        'role' => UserRole::SuperAdmin,
    ]))->toThrow(DomainException::class, 'Solo la cuenta designada puede recibir el rol de superadministrador.');

    // Creating designated superadmin succeeds
    $superAdmin = User::factory()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::SuperAdmin,
    ]);

    expect($superAdmin->role)->toBe(UserRole::SuperAdmin)
        ->and($superAdmin->isSuperAdmin())->toBeTrue();

    // Cannot create a second superadmin
    expect(fn () => User::factory()->create([
        'email' => 'another@example.com',
        'role' => UserRole::SuperAdmin,
    ]))->toThrow(DomainException::class);
});

it('protects superadmin against deletion, demotion, deactivation, and email change', function (): void {
    $superAdmin = User::factory()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ]);

    // Deletion is blocked
    expect(fn () => $superAdmin->delete())
        ->toThrow(DomainException::class, 'La cuenta superadministradora está protegida y no puede ser eliminada.');

    // Demotion is blocked
    expect(fn () => $superAdmin->update(['role' => UserRole::Admin]))
        ->toThrow(DomainException::class, 'La cuenta superadministradora no puede ser degradada ni cambiar de rol.');
    $superAdmin->refresh();

    // Email change is blocked
    expect(fn () => $superAdmin->update(['email' => 'new-email@example.com']))
        ->toThrow(DomainException::class, 'El correo del superadministrador está protegido y no puede ser modificado.');
    $superAdmin->refresh();

    // Deactivation is blocked
    expect(fn () => $superAdmin->update(['is_active' => false]))
        ->toThrow(DomainException::class, 'La cuenta superadministradora no puede ser desactivada.');

    $superAdmin->refresh();
    expect($superAdmin->role)->toBe(UserRole::SuperAdmin)
        ->and($superAdmin->email)->toBe(User::SUPER_ADMIN_EMAIL)
        ->and($superAdmin->is_active)->toBeTrue();
});

it('protects superadmin against bulk operations and indirect updates', function (): void {
    $superAdmin = User::factory()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ]);
    $editor = User::factory()->create(['role' => UserRole::Editor, 'is_active' => true]);

    // Mass delete on User model query deletes other users but preserves superadmin
    User::query()->delete();

    expect(User::where('id', $editor->id)->exists())->toBeFalse()
        ->and(User::where('id', $superAdmin->id)->exists())->toBeTrue();

    // Mass update deactivating users excludes superadmin
    User::query()->update(['is_active' => false]);
    expect($superAdmin->fresh()->is_active)->toBeTrue();

    // Direct mass delete specifically targeting superadmin email is blocked
    User::where('email', User::SUPER_ADMIN_EMAIL)->delete();
    expect(User::where('id', $superAdmin->id)->exists())->toBeTrue();
});

it('creates or links superadmin idempotently through setup-super-admin command preserving credentials', function (): void {
    // 1. Initial creation via command
    $exitCode = Artisan::call('app:setup-super-admin', [
        '--name' => 'Alejandro Tech',
        '--password' => 'SuperSecret123!',
    ]);

    expect($exitCode)->toBe(0);

    $superAdmin = User::where('email', User::SUPER_ADMIN_EMAIL)->first();
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->role)->toBe(UserRole::SuperAdmin)
        ->and($superAdmin->name)->toBe('Alejandro Tech');

    // 2. Re-running command when account already exists is idempotent and does not fail
    $reRunExit = Artisan::call('app:setup-super-admin', [
        '--password' => 'AnotherPassword999!',
    ]);
    expect($reRunExit)->toBe(0);

    // 3. Linking existing user with different role preserves their password and data
    DB::table('users')->where('email', User::SUPER_ADMIN_EMAIL)->delete();
    $existing = User::factory()->create([
        'name' => 'Pre-existing Name',
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::Editor,
        'password' => bcrypt('OriginalPassword123!'),
    ]);

    $linkExit = Artisan::call('app:setup-super-admin');
    expect($linkExit)->toBe(0);

    $linked = $existing->fresh();
    expect($linked->role)->toBe(UserRole::SuperAdmin)
        ->and($linked->name)->toBe('Pre-existing Name')
        ->and(password_verify('OriginalPassword123!', $linked->password))->toBeTrue();
});

it('verifies role-based panel access and ensures client user cannot access admin panel', function (): void {
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $clientUser = User::factory()->create(['role' => UserRole::User]);
    $inactiveAdmin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);

    $panel = Filament::getPanel('admin');

    expect($superAdmin->canAccessPanel($panel))->toBeTrue()
        ->and($admin->canAccessPanel($panel))->toBeTrue()
        ->and($editor->canAccessPanel($panel))->toBeTrue()
        ->and($clientUser->canAccessPanel($panel))->toBeFalse()
        ->and($inactiveAdmin->canAccessPanel($panel))->toBeFalse();

    // HTTP check
    $this->actingAs($clientUser)->get('/admin')->assertForbidden();
    $this->actingAs($inactiveAdmin)->get('/admin')->assertForbidden();
    $this->actingAs($editor)->get('/admin')->assertOk();
    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($superAdmin)->get('/admin')->assertOk();
});

it('verifies granular permissions and server-side authorization by resource and action', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $user = User::factory()->create(['role' => UserRole::User]);

    // SuperAdmin has all permissions
    foreach (Permission::cases() as $permission) {
        expect($superAdmin->hasPermission($permission))->toBeTrue();
    }

    // Editor has content permissions but not user/settings/delete permissions
    expect($editor->hasPermission(Permission::ViewPost))->toBeTrue()
        ->and($editor->hasPermission(Permission::CreatePost))->toBeTrue()
        ->and($editor->hasPermission(Permission::UpdatePost))->toBeTrue()
        ->and($editor->hasPermission(Permission::DeletePost))->toBeFalse()
        ->and($editor->hasPermission(Permission::ViewUsers))->toBeFalse()
        ->and($editor->hasPermission(Permission::ViewSettings))->toBeFalse();

    // Client user has zero permissions
    foreach (Permission::cases() as $permission) {
        expect($user->hasPermission($permission))->toBeFalse();
    }

    // Gate checks on Post policy
    $post = Post::factory()->create();

    $this->actingAs($editor);
    expect(Gate::allows('view', $post))->toBeTrue()
        ->and(Gate::allows('update', $post))->toBeTrue()
        ->and(Gate::allows('delete', $post))->toBeFalse();

    $this->actingAs($admin);
    expect(Gate::allows('view', $post))->toBeTrue()
        ->and(Gate::allows('update', $post))->toBeTrue()
        ->and(Gate::allows('delete', $post))->toBeTrue();
});

it('prevents privilege escalation and forbids admin from modifying or deleting superadmin', function (): void {
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    // Admin cannot delete superadmin via policy
    expect(Gate::forUser($admin)->allows('delete', $superAdmin))->toBeFalse();

    // Admin cannot update superadmin via policy
    expect(Gate::forUser($admin)->allows('update', $superAdmin))->toBeFalse();

    // Admin cannot assign super_admin role to anyone
    $userPolicy = new UserPolicy;
    expect($userPolicy->assignRole($admin, $editor, UserRole::SuperAdmin))->toBeFalse();

    // Admin cannot grant a permission they lack
    expect(fn () => $editor->givePermission(Permission::ViewUsers, $editor))
        ->toThrow(AuthorizationException::class, 'No puedes otorgar un permiso que no posees.');

    // Admin cannot elevate their own permissions directly
    expect(fn () => $admin->givePermission(Permission::ViewUsers, $admin))
        ->toThrow(AuthorizationException::class, 'No puedes elevar tus propios privilegios.');
});

it('rejects manipulated role assignments in EditUser Livewire component', function (): void {
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($admin);

    // Attempting to assign super_admin to editor via form is rejected
    Livewire::test(EditUser::class, ['record' => $editor->id])
        ->fillForm([
            'role' => UserRole::SuperAdmin->value,
        ])
        ->call('save')
        ->assertHasFormErrors(['role']);

    // Admin attempting to edit superadmin record is forbidden
    Livewire::test(EditUser::class, ['record' => $superAdmin->id])
        ->assertForbidden();
});

it('ensures RolesAndPermissionsSeeder is completely idempotent', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $count1 = DB::table('permissions')->count();
    $rolePermissions1 = DB::table('role_permissions')->count();

    expect($count1)->toBe(count(Permission::cases()));

    // Seed again
    $this->seed(RolesAndPermissionsSeeder::class);
    $count2 = DB::table('permissions')->count();
    $rolePermissions2 = DB::table('role_permissions')->count();

    expect($count2)->toBe($count1)
        ->and($rolePermissions2)->toBe($rolePermissions1);
});

it('preserves backwards compatibility for existing administrator role and references', function (): void {
    // Both Admin and Administrator are recognized administrator roles
    expect(UserRole::Administrator->isAdmin())->toBeTrue()
        ->and(UserRole::Admin->isAdmin())->toBeTrue()
        ->and(UserRole::Admin->value)->toBe('admin')
        ->and(UserRole::Administrator->value)->toBe('administrator');

    // Legacy 'administrator' role is fully functional
    $legacyUser = new User;
    $legacyUser->role = 'administrator';
    expect($legacyUser->role)->toBe(UserRole::Administrator)
        ->and($legacyUser->isAdmin())->toBeTrue();

    // UserRole::fromValue
    expect(UserRole::fromValue('administrator'))->toBe(UserRole::Administrator)
        ->and(UserRole::fromValue('admin'))->toBe(UserRole::Admin)
        ->and(UserRole::fromValue('super_admin'))->toBe(UserRole::SuperAdmin);
});
