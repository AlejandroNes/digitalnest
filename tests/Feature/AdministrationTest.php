<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\GeneralSetting;
use App\Models\User;
use App\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/users')->assertRedirect('/admin/login');
        $this->get('/admin/general-settings')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_administrator_can_open_dashboard_users_and_settings(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Usuarios');
        $this->get('/admin/users')->assertOk()->assertSee($admin->email);
        $this->get('/admin/general-settings')->assertOk()->assertSee('Configuración general');
    }

    public function test_editor_can_access_dashboard_and_profile_but_not_critical_resources(): void
    {
        $editor = User::factory()->create();
        $this->actingAs($editor);
        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/users/'.$editor->id.'/edit')->assertForbidden();
        $this->get('/admin/general-settings')->assertForbidden();
        Livewire::test(CreateUser::class)->assertForbidden();
    }

    #[TestWith(['viewAny'])]
    #[TestWith(['create'])]
    #[TestWith(['view'])]
    #[TestWith(['update'])]
    #[TestWith(['delete'])]
    public function test_user_policy_permission_matrix(string $ability): void
    {
        $admin = User::factory()->administrator()->create();
        $editor = User::factory()->create();
        $inactive = User::factory()->administrator()->inactive()->create();
        $target = User::factory()->create();
        $subject = in_array($ability, ['viewAny', 'create'], true) ? User::class : $target;

        $this->assertTrue(Gate::forUser($admin)->allows($ability, $subject));
        $this->assertFalse(Gate::forUser($editor)->allows($ability, $subject));
        $this->assertFalse(Gate::forUser($inactive)->allows($ability, $subject));
    }

    #[TestWith(['viewAny'])]
    #[TestWith(['view'])]
    #[TestWith(['update'])]
    public function test_settings_policy_permission_matrix(string $ability): void
    {
        $admin = User::factory()->administrator()->create();
        $editor = User::factory()->create();
        $inactive = User::factory()->administrator()->inactive()->create();
        $subject = $ability === 'viewAny' ? GeneralSetting::class : GeneralSetting::current();

        $this->assertTrue(Gate::forUser($admin)->allows($ability, $subject));
        $this->assertFalse(Gate::forUser($editor)->allows($ability, $subject));
        $this->assertFalse(Gate::forUser($inactive)->allows($ability, $subject));
    }

    public function test_inactive_users_cannot_enter_panel_or_login(): void
    {
        $user = User::factory()->inactive()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->app['auth']->forgetGuards();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();
    }

    public function test_editor_can_login_and_logout(): void
    {
        $editor = User::factory()->create();
        Livewire::test(Login::class)->fillForm(['email' => $editor->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($editor);
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_login_attempts_are_limited_and_can_resume_after_cooldown(): void
    {
        $editor = User::factory()->create();
        $component = Livewire::test(Login::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->fillForm(['email' => $editor->email, 'password' => 'incorrect'])
                ->call('authenticate')->assertHasFormErrors(['email']);
        }

        $component->fillForm(['email' => $editor->email, 'password' => 'password'])
            ->call('authenticate')->assertNotified();
        $this->assertGuest();

        $this->travel(61)->seconds();
        $component->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($editor);
    }

    public function test_administrator_can_create_edit_assign_role_and_delete_another_user(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Nueva editora', 'email' => 'editor@example.test',
            'password' => 'Secure-Password123!', 'role' => 'editor', 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $editor = User::query()->where('email', 'editor@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Secure-Password123!', $editor->password));
        $this->assertSame(UserRole::Editor, $editor->role);

        Livewire::test(EditUser::class, ['record' => $editor->id])->fillForm([
            'name' => 'Nueva administradora', 'role' => 'administrator', 'is_active' => false,
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame(UserRole::Administrator, $editor->fresh()->role);
        $this->assertFalse($editor->fresh()->is_active);
        $this->assertTrue(Hash::check('Secure-Password123!', $editor->fresh()->password));

        Livewire::test(EditUser::class, ['record' => $editor->id])->callAction(DeleteAction::class);
        $this->assertModelMissing($editor);
    }

    #[TestWith(['name', '', 'required'])]
    #[TestWith(['email', 'invalid-email', 'email'])]
    #[TestWith(['email', 'existing@example.test', 'unique'])]
    #[TestWith(['password', 'short', null])]
    #[TestWith(['role', 'superuser', null])]
    public function test_user_creation_rejects_invalid_input(string $field, string $value, ?string $rule): void
    {
        $this->actingAs(User::factory()->administrator()->create(['email' => 'existing@example.test']));
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Editor', 'email' => 'new@example.test', 'password' => 'Secure-Password123!',
            'role' => 'editor', 'is_active' => true, $field => $value,
        ])->call('create')->assertHasFormErrors($rule ? [$field => $rule] : [$field]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_administrator_cannot_delete_or_disable_or_demote_self_through_tampered_form(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('delete', $admin));
        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->set('data.role', 'editor')->set('data.is_active', false)->call('save')->assertHasNoFormErrors();
        $this->assertSame(UserRole::Administrator, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_permission_is_rechecked_when_role_changes_after_opening_user_form(): void
    {
        $admin = User::factory()->administrator()->create();
        $editor = User::factory()->create();
        $this->actingAs($admin);
        $component = Livewire::test(EditUser::class, ['record' => $editor->id])->fillForm(['name' => 'Forbidden update']);
        $admin->update(['role' => UserRole::Editor]);
        $component->call('save')->assertForbidden();
        $this->assertNotSame('Forbidden update', $editor->fresh()->name);
    }

    public function test_editor_can_change_own_profile_without_escalating_role(): void
    {
        $editor = User::factory()->create();
        $this->actingAs($editor);
        Livewire::test(EditProfile::class)->fillForm(['name' => 'Perfil actualizado'])
            ->set('data.role', 'administrator')->set('data.is_active', false)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Perfil actualizado', $editor->fresh()->name);
        $this->assertSame(UserRole::Editor, $editor->fresh()->role);
        $this->assertTrue($editor->fresh()->is_active);
    }

    public function test_password_change_requires_current_password(): void
    {
        $editor = User::factory()->create();
        $this->actingAs($editor);
        $component = Livewire::test(EditProfile::class)->fillForm([
            'password' => 'New-SecurePassword123!', 'passwordConfirmation' => 'New-SecurePassword123!',
            'currentPassword' => 'incorrect',
        ])->call('save')->assertHasFormErrors(['currentPassword']);
        $this->assertTrue(Hash::check('password', $editor->fresh()->password));
        $component->fillForm(['currentPassword' => 'password'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('New-SecurePassword123!', $editor->fresh()->password));
    }

    public function test_password_recovery_sends_notification_and_resets_password_with_valid_token(): void
    {
        Notification::fake();
        $editor = User::factory()->create();
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $editor->email])->call('request')->assertHasNoFormErrors();
        Notification::assertSentTo($editor, ResetPasswordNotification::class);
        $token = Notification::sent($editor, ResetPasswordNotification::class)->first()->token;
        Livewire::test(ResetPassword::class, ['token' => $token, 'email' => $editor->email])
            ->fillForm(['password' => 'Recovered-Password123!', 'passwordConfirmation' => 'Recovered-Password123!'])
            ->call('resetPassword')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('Recovered-Password123!', $editor->fresh()->password));
        $this->assertFalse(Password::tokenExists($editor, $token));
    }
}
