<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crea un administrador nuevo con contraseña oculta, sin credenciales predeterminadas';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta el comando de forma interactiva para introducir la contraseña de manera segura.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Nombre'),
            'email' => $this->ask('Correo'),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos)'),
            'password_confirmation' => $this->secret('Confirmar contraseña'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', Rule::notIn([User::SUPER_ADMIN_EMAIL])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([
            ...$validator->safe()->only(['name', 'email', 'password']),
            'role' => UserRole::Administrator,
            'is_active' => true,
        ]);

        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
