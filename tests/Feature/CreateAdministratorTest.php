<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_creates_administrator_without_default_credentials(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Administradora')
            ->expectsQuestion('Correo', 'admin@example.test')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)', 'Secure-Password123!')
            ->expectsQuestion('Confirmar contraseña', 'Secure-Password123!')
            ->expectsOutput('Administrador creado.')->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame(UserRole::Administrator, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Secure-Password123!', $admin->password));
    }

    #[TestWith(['invalid', 'Secure-Password123!', 'Secure-Password123!'])]
    #[TestWith(['admin@example.test', 'short', 'short'])]
    #[TestWith(['admin@example.test', 'Secure-Password123!', 'Different-Password123!'])]
    public function test_command_rejects_invalid_credentials(string $email, string $password, string $confirmation): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Administradora')
            ->expectsQuestion('Correo', $email)
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)', $password)
            ->expectsQuestion('Confirmar contraseña', $confirmation)->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_does_not_promote_or_replace_existing_account(): void
    {
        $editor = User::factory()->create(['email' => 'existing@example.test']);
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Administradora')
            ->expectsQuestion('Correo', $editor->email)
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)', 'Secure-Password123!')
            ->expectsQuestion('Confirmar contraseña', 'Secure-Password123!')->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserRole::Editor, $editor->fresh()->role);
    }

    public function test_command_refuses_non_interactive_creation(): void
    {
        $this->artisan('app:create-admin', ['--no-interaction' => true])->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_default_seeder_does_not_create_accounts(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
    }
}
