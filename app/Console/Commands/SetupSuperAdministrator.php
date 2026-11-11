<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SetupSuperAdministrator extends Command
{
    protected $signature = 'app:setup-super-admin {--password= : Contraseña segura para creación nueva} {--name= : Nombre del superadministrador}';

    protected $description = 'Crea o vincula la cuenta superadministradora única (alejandrones.tech@gmail.com) de forma segura e idempotente';

    public function handle(): int
    {
        $existing = User::query()->where('email', User::SUPER_ADMIN_EMAIL)->first();

        if ($existing) {
            if ($existing->role === UserRole::SuperAdmin && $existing->is_active) {
                $this->info('La cuenta superadministradora ya existe y está vinculada.');

                return self::SUCCESS;
            }

            $existing->role = UserRole::SuperAdmin;
            $existing->is_active = true;
            $existing->save();

            $this->info('La cuenta existente ha sido vinculada como superadministrador conservando sus credenciales.');

            return self::SUCCESS;
        }

        $password = $this->option('password');
        $name = $this->option('name');

        if (blank($password)) {
            if (! $this->input->isInteractive()) {
                $this->error('Ejecuta el comando interactivamente para introducir la contraseña o pasa --password.');

                return self::FAILURE;
            }

            $name ??= $this->ask('Nombre', 'Superadministrador');
            $password = $this->secret('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)');
            $passwordConfirmation = $this->secret('Confirmar contraseña');

            $validator = Validator::make([
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]);
        } else {
            $name ??= 'Superadministrador';

            $validator = Validator::make([
                'name' => $name,
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]);
        }

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        $this->info('Superadministrador creado exitosamente.');

        return self::SUCCESS;
    }
}
