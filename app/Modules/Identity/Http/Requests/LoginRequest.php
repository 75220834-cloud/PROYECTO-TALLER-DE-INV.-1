<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login del personal interno.
 *
 * Solo aplica al panel de soporte y administracion. El docente no pasa por
 * aqui: escanea el QR y entra directo (plan 16.1).
 *
 * El limite de intentos se cuenta por correo + IP, no solo por IP. Contar
 * solo por IP en una red institucional donde muchos comparten la salida
 * significaria que un atacante puede bloquear a todo el equipo de soporte
 * fallando adrede unas cuantas veces. Es el mismo razonamiento del riesgo
 * R18 aplicado al login.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'El correo no tiene un formato valido.',
            'password.required' => 'Ingresa tu contrasena.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);

            // Mensaje deliberadamente ambiguo: no revela si el correo existe.
            // Distinguir "usuario no encontrado" de "contrasena incorrecta"
            // permitiria enumerar las cuentas del personal de soporte.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos. Vuelve a intentarlo en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|'.$this->ip()
        );
    }
}
