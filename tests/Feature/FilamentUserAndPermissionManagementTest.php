<?php

use App\Filament\Pages\RolesAndPermissions;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\RolePermission;
use App\Models\User;
use App\Permission;
use App\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows authorized administrator to list users, search by name/email, and filter by role and active status', function (): void {
    $admin = User::factory()->administrator()->create();
    $editor = User::factory()->create(['name' => 'Editor Exclusivo', 'email' => 'editor.exclusivo@example.test', 'role' => UserRole::Editor, 'is_active' => true]);
    $inactive = User::factory()->create(['name' => 'Inactivo', 'email' => 'inactivo@example.test', 'role' => UserRole::User, 'is_active' => false]);

    $this->actingAs($admin);

    $component = Livewire::test(ListUsers::class);

    // Search by name
    $component->searchTable('Editor Exclusivo')
        ->assertCanSeeTableRecords([$editor])
        ->assertCanNotSeeTableRecords([$inactive]);

    // Search by email
    $component->searchTable('inactivo@example.test')
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$editor]);

    // Reset search and filter by role
    $component->searchTable('')
        ->filterTable('role', UserRole::Editor->value)
        ->assertCanSeeTableRecords([$editor])
        ->assertCanNotSeeTableRecords([$inactive]);

    // Filter by active status
    $component->filterTable('role', null)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$editor]);
});

it('allows authorized administrator to create a user with confirmation, validation, and secure password hashing', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);

    // Validation fails if password confirmation does not match
    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.test',
        'password' => 'Secure-Password123!',
        'password_confirmation' => 'Different-Password123!',
        'role' => 'editor',
        'is_active' => true,
    ])->call('create')->assertHasFormErrors(['password_confirmation']);

    // Creation succeeds when confirmation matches
    Livewire::test(CreateUser::class)->fillForm([
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.test',
        'password' => 'Secure-Password123!',
        'password_confirmation' => 'Secure-Password123!',
        'role' => 'editor',
        'is_active' => true,
    ])->call('create')->assertHasNoFormErrors();

    $created = User::query()->where('email', 'nuevo@example.test')->firstOrFail();
    expect($created->name)->toBe('Nuevo Usuario')
        ->and($created->role)->toBe(UserRole::Editor)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('Secure-Password123!', $created->password))->toBeTrue();
});

it('strictly protects super administrator account from being edited or deleted in UserResource', function (): void {
    $superAdmin = User::factory()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::SuperAdmin,
    ]);

    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);

    // Actions are hidden on the table for superadmin
    $table = Livewire::test(ListUsers::class);
    $table->assertActionHidden(TestAction::make('edit')->table($superAdmin))
        ->assertActionHidden(TestAction::make('delete')->table($superAdmin))
        ->assertActionHidden(TestAction::make('resetPassword')->table($superAdmin))
        ->assertActionHidden(TestAction::make('toggleActive')->table($superAdmin));

    // Direct access to edit page is forbidden
    Livewire::test(EditUser::class, ['record' => $superAdmin->id])->assertForbidden();

    // Even superadmin cannot edit the superadmin record via EditUser
    $this->actingAs($superAdmin);
    Livewire::test(EditUser::class, ['record' => $superAdmin->id])->assertForbidden();
});

it('allows manual password reset on authorized users with confirmation and hashing while protecting superadmin', function (): void {
    $admin = User::factory()->administrator()->create();
    $target = User::factory()->create(['password' => Hash::make('Old-Password123!')]);
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);

    $this->actingAs($admin);

    // Resetting regular user password succeeds
    Livewire::test(ListUsers::class)->callAction(
        TestAction::make('resetPassword')->table($target),
        [
            'password' => 'New-SecretPassword123!',
            'password_confirmation' => 'New-SecretPassword123!',
        ]
    )->assertHasNoErrors();

    expect(Hash::check('New-SecretPassword123!', $target->fresh()->password))->toBeTrue();

    // Resetting superadmin password is hidden on table and blocked
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('resetPassword')->table($superAdmin));

    expect(Hash::check('password', $superAdmin->fresh()->password))->toBeTrue();
});

it('allows activating and deactivating accounts with confirmation while forbidding self-deactivation and superadmin deactivation', function (): void {
    $admin = User::factory()->administrator()->create();
    $target = User::factory()->create(['is_active' => true]);
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);

    $this->actingAs($admin);

    // Deactivate regular account
    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleActive')->table($target))
        ->assertHasNoErrors();

    expect($target->fresh()->is_active)->toBeFalse();

    // Reactivate regular account
    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleActive')->table($target))
        ->assertHasNoErrors();

    expect($target->fresh()->is_active)->toBeTrue();

    // Cannot deactivate self (action is hidden)
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('toggleActive')->table($admin));

    expect($admin->fresh()->is_active)->toBeTrue();

    // Cannot deactivate superadmin (action is hidden)
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('toggleActive')->table($superAdmin));

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

it('allows deleting regular users while preventing self-deletion and superadmin deletion', function (): void {
    $admin = User::factory()->administrator()->create();
    $target = User::factory()->create();
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);

    $this->actingAs($admin);

    // Deleting regular user succeeds
    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($target))
        ->assertHasNoErrors();

    expect(User::query()->find($target->id))->toBeNull();

    // Self-deletion is hidden
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($admin));

    // Superadmin deletion is hidden
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($superAdmin));

    expect(User::query()->find($superAdmin->id))->not->toBeNull();
});

it('allows super administrator to configure and persist admin role permissions in RolesAndPermissions page', function (): void {
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $this->actingAs($superAdmin);

    $component = Livewire::test(RolesAndPermissions::class);

    expect($component->get('canManageAdminRole'))->toBeTrue();

    // Modify administrator permissions: grant only posts.view and posts.create
    $component->set('adminPermissions', ['posts.view', 'posts.create'])
        ->call('saveAdminPermissions');

    RolePermission::clearCache();

    expect(RolePermission::allows(UserRole::Admin, Permission::ViewPost))->toBeTrue()
        ->and(RolePermission::allows(UserRole::Admin, Permission::CreatePost))->toBeTrue()
        ->and(RolePermission::allows(UserRole::Admin, Permission::DeletePost))->toBeFalse();
});

it('forbids ordinary administrator from mutating admin role permissions to prevent self-elevation', function (): void {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);

    $component = Livewire::test(RolesAndPermissions::class);
    expect($component->get('canManageAdminRole'))->toBeFalse();

    // Attempting to call saveAdminPermissions aborts 403
    $component->set('adminPermissions', ['posts.view'])
        ->call('saveAdminPermissions')
        ->assertForbidden();
});

it('allows assigning specific individual permissions on EditUser', function (): void {
    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($superAdmin);

    expect($editor->hasPermission(Permission::ViewRedirect))->toBeFalse();

    // Assign individual permission 'redirects.view' via EditUser form
    Livewire::test(EditUser::class, ['record' => $editor->id])
        ->fillForm([
            'custom_permissions' => [Permission::ViewRedirect->value],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($editor->fresh()->hasPermission(Permission::ViewRedirect))->toBeTrue();
});

it('restricts navigation and page access so editors cannot see or access user/permission management', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $this->actingAs($editor);

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/roles-and-permissions')->assertForbidden();

    Livewire::test(ListUsers::class)->assertForbidden();
    Livewire::test(RolesAndPermissions::class)->assertForbidden();
});
