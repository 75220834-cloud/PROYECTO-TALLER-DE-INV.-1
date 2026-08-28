<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Gestión de usuarios y roles del personal interno (plan CU-A-05).
 *
 * Solo cubre a quien entra al panel. El docente no aparece aquí: no tiene
 * cuenta ni la va a tener, y esa es una decisión cerrada del plan (D-2).
 *
 * UN USUARIO NO SE BORRA, SE DESACTIVA. Sus incidencias atendidas, sus
 * cambios de estado y su rastro en la auditoría tienen que seguir siendo
 * legibles: borrarlo dejaría huecos en el historial que la investigación va
 * a analizar. Desactivar quita el acceso, que es lo que de verdad se busca
 * cuando alguien deja el equipo.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'roles' => $this->roles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'role' => ['required', Rule::in($this->roles())],
        ], $this->messages());

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        // Se audita el ROL, nunca la contraseña. Registrar credenciales en
        // una tabla de auditoría las convierte en un dato filtrable.
        $this->audit->record('user.created', $user, ['role' => $data['role']]);

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario «{$user->name}» creado con el rol {$data['role']}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => $this->roles()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],

            // La contraseña solo se cambia si se escribe: dejarla vacía en
            // una edición de nombre no debe reiniciarla.
            'password' => ['nullable', 'confirmed', Password::min(10)],
            'role' => ['required', Rule::in($this->roles())],
        ], $this->messages());

        $previous = $user->getRoleNames()->first();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ] + (filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []));

        $user->syncRoles([$data['role']]);

        $this->audit->record('user.updated', $user, [
            'role_from' => $previous,
            'role_to' => $data['role'],
            'password_changed' => filled($data['password'] ?? null),
        ]);

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario «{$user->name}» actualizado.");
    }

    /**
     * Los roles del sistema, tal como los define el seeder.
     *
     * Se leen de la base y no de una lista escrita aquí: si alguien añade un
     * rol, esta pantalla debe conocerlo sin que haya que tocarla.
     *
     * @return list<string>
     */
    private function roles(): array
    {
        return Role::orderBy('name')->pluck('name')->all();
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'role.required' => 'Elige un rol: sin él la cuenta no podría hacer nada.',
        ];
    }
}
