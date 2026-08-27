<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios DEMO, uno por rol, para poder probar los permisos.
 *
 * La contrasena se toma de DEMO_USER_PASSWORD si existe; si no, se genera
 * una aleatoria y se muestra una sola vez por consola.
 *
 * Por que NO se escribe una contrasena fija en el codigo: este archivo va
 * al repositorio. Una clave como "password" aqui sobrevive al piloto, llega
 * al servidor institucional y queda publicada en Git para siempre. El plan
 * lo prohibe expresamente (secretos fuera del repositorio) y es de los
 * errores mas faciles de cometer y mas caros de deshacer.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('incidencias.demo_user_password');
        $generated = false;

        if (blank($password)) {
            $password = str()->password(16, symbols: false);
            $generated = true;
        }

        $users = [
            ['DEMO Administrador', 'admin@demo.local', 'admin'],
            ['DEMO Coordinador de soporte', 'coordinador@demo.local', 'coordinator'],
            ['DEMO Tecnico', 'tecnico@demo.local', 'technician'],
            ['DEMO Gestor de conocimiento', 'conocimiento@demo.local', 'knowledge_manager'],
            ['DEMO Investigador', 'investigador@demo.local', 'researcher'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($password)]
            );

            $user->syncRoles([$role]);
        }

        $this->command->info('  Usuarios DEMO: '.count($users).' (uno por rol)');

        if ($generated) {
            $this->command->warn("  Contrasena generada para TODOS los usuarios demo: {$password}");
            $this->command->warn('  Anotala: no se vuelve a mostrar. Define DEMO_USER_PASSWORD en .env para fijarla.');
        }
    }
}
