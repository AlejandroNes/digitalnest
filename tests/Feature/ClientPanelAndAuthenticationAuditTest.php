<?php

use App\Models\Post;
use App\Models\User;
use App\Permission;
use App\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('disables client panel routes by default and returns 404 on all /panel paths', function (): void {
    config(['features.client_panel_enabled' => false]);

    $this->get('/panel')->assertNotFound();
    $this->get('/panel/login')->assertNotFound();
    $this->get('/panel/profile')->assertNotFound();
    $this->get('/panel/password-reset/request')->assertNotFound();
});

it('makes client panel login accessible when client panel is explicitly enabled in configuration', function (): void {
    config(['features.client_panel_enabled' => true]);

    $this->get('/panel/login')->assertSuccessful()
        ->assertSee('Sign in');
});

it('allows active client user to access /panel when enabled and strictly forbids admin, editor, and superadmin', function (): void {
    config(['features.client_panel_enabled' => true]);

    $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'role' => UserRole::SuperAdmin]);
    $admin = User::factory()->administrator()->create();
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $clientUser = User::factory()->create(['role' => UserRole::User, 'is_active' => true]);
    $inactiveClient = User::factory()->create(['role' => UserRole::User, 'is_active' => false]);

    // Active client user accesses /panel
    $this->actingAs($clientUser)->get('/panel')->assertOk()
        ->assertSee('Mi Cuenta')
        ->assertSee('Bienvenido, '.$clientUser->name);

    // Inactive client is denied access
    $this->actingAs($inactiveClient)->get('/panel')->assertForbidden();

    // Administrative roles are denied access to client panel
    $this->actingAs($admin)->get('/panel')->assertForbidden();
    $this->actingAs($editor)->get('/panel')->assertForbidden();
    $this->actingAs($superAdmin)->get('/panel')->assertForbidden();
});

it('strictly forbids client user from accessing /admin and any administrative resources', function (): void {
    $clientUser = User::factory()->create(['role' => UserRole::User, 'is_active' => true]);
    $this->actingAs($clientUser);

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/posts')->assertForbidden();
    $this->get('/admin/general-settings')->assertForbidden();
    $this->get('/admin/roles-and-permissions')->assertForbidden();
});

it('requires current password to update password in profile on client panel', function (): void {
    config(['features.client_panel_enabled' => true]);
    Filament::setCurrentPanel(Filament::getPanel('panel'));

    $clientUser = User::factory()->create([
        'role' => UserRole::User,
        'password' => Hash::make('Current-Secret123!'),
    ]);

    $this->actingAs($clientUser);

    // Wrong current password fails validation
    $component = Livewire::test(EditProfile::class)->fillForm([
        'currentPassword' => 'wrong-password',
        'password' => 'New-SecretPassword123!',
        'passwordConfirmation' => 'New-SecretPassword123!',
    ])->call('save')->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('Current-Secret123!', $clientUser->fresh()->password))->toBeTrue();

    // Correct current password succeeds
    $component->fillForm([
        'currentPassword' => 'Current-Secret123!',
        'password' => 'New-SecretPassword123!',
        'passwordConfirmation' => 'New-SecretPassword123!',
    ])->call('save')->assertHasNoFormErrors();

    expect(Hash::check('New-SecretPassword123!', $clientUser->fresh()->password))->toBeTrue();
});

it('immediately revokes access and aborts when an active account is deactivated in database', function (): void {
    $admin = User::factory()->administrator()->create(['is_active' => true]);
    $this->actingAs($admin);

    $this->get('/admin')->assertOk();

    // Account is deactivated in database
    $admin->update(['is_active' => false]);

    // Next request to protected route is rejected with 403
    $this->get('/admin')->assertForbidden();
});

it('performs complete audit verifying the single superadmin invariant across all layers', function (): void {
    $superAdmin = User::factory()->create([
        'email' => User::SUPER_ADMIN_EMAIL,
        'role' => UserRole::SuperAdmin,
    ]);

    // Only one superadmin allowed
    expect(User::query()->where('role', UserRole::SuperAdmin)->count())->toBe(1);

    // Attempting to create a second superadmin throws exception
    expect(fn () => User::factory()->create([
        'email' => 'second.superadmin@example.com',
        'role' => UserRole::SuperAdmin,
    ]))->toThrow(DomainException::class);

    // Cannot demote superadmin
    expect(fn () => $superAdmin->update(['role' => UserRole::Editor]))->toThrow(DomainException::class);

    // Cannot deactivate superadmin
    expect(fn () => $superAdmin->update(['is_active' => false]))->toThrow(DomainException::class);

    // Cannot delete superadmin
    expect(fn () => $superAdmin->delete())->toThrow(DomainException::class);

    // Bulk deletion query automatically excludes superadmin
    User::query()->delete();
    expect(User::query()->where('email', User::SUPER_ADMIN_EMAIL)->exists())->toBeTrue();
});

it('performs complete audit verifying editor content restrictions and administrative prohibitions', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $post = Post::factory()->create();

    $this->actingAs($editor);

    // Content permissions granted
    expect(Gate::allows('view', $post))->toBeTrue()
        ->and(Gate::allows('update', $post))->toBeTrue()
        ->and(Gate::allows('create', Post::class))->toBeTrue();

    // Post deletion denied for editor
    expect(Gate::allows('delete', $post))->toBeFalse();

    // User management denied for editor
    expect(Gate::allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::allows('create', User::class))->toBeFalse()
        ->and(Gate::allows('update', $editor))->toBeFalse();

    // Settings denied for editor
    expect($editor->hasPermission(Permission::ViewSettings))->toBeFalse()
        ->and($editor->hasPermission(Permission::UpdateSettings))->toBeFalse();
});
