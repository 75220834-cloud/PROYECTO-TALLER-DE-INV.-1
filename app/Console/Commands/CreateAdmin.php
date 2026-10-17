<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Crea la primera cuenta de administrador.
 *
 * En produccion el sistema no siembra usuarios: los de demostracion llevan
 * contrasenas conocidas y no deben existir en el servidor. Pero sin ningun
 * usuario no hay forma de entrar al panel para crear el primero. Este
 * comando cierra ese hueco.
 *
 * LA CONTRASENA SOLO SE ESCRIBE AQUI
 *
 * No se acepta como argumento ni como opcion: quedaria guardada en el
 * historial de la consola. Se pide con la entrada oculta, la escribe quien
 * va a usar la cuenta, y por eso el comando se niega a correr sin una
 * persona delante.
 */
final class CreateAdmin extends Command
{
    protected $signature = 'usuarios:crear-admin';

    protected $description = 'Crea una cuenta de administrador pidiendo los datos por consola';

    public function handle(AuditLogger $audit): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando necesita a una persona delante: pide la contraseña por teclado.');

            return self::FAILURE;
        }

        if (Role::where('name', 'admin')->doesntExist()) {
            $this->error('Todavía no existen los roles. Ejecuta antes: php artisan migrate --seed');

            return self::FAILURE;
        }

        $this->line('Vas a crear una cuenta con TODOS los permisos del sistema.');
        $this->line('La contraseña no se muestra mientras la escribes.');
        $this->newLine();

        $data = [
            'name' => trim((string) $this->ask('Nombre completo')),
            'email' => mb_strtolower(trim((string) $this->ask('Correo (con este se inicia sesión)')), 'UTF-8'),
            'password' => (string) $this->secret('Contraseña (mínimo 10 caracteres)'),
            'password_confirmation' => (string) $this->secret('Repite la contraseña'),
        ];

        // Las mismas reglas que el formulario del panel: una cuenta creada
        // por consola no puede ser mas debil que una creada por pantalla.
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            $this->line('No se creó ninguna cuenta. Vuelve a ejecutar el comando.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles(['admin']);

        // Se audita el rol y el origen, nunca la contrasena.
        $audit->record('user.created', $user, ['role' => 'admin', 'via' => 'consola']);

        $this->newLine();
        $this->info("Cuenta creada: {$user->name} <{$user->email}> con el rol de administrador.");
        $this->line('Entra al panel en /entrar desde esta misma computadora.');

        return self::SUCCESS;
    }
}
