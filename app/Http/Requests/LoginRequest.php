<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Los errores se muestran dentro del cuadro de "Iniciar sesión". */
    protected $errorBag = 'login';

    /** Hash falso para que tarde lo mismo exista o no el correo (no se puede adivinar qué cuentas existen). */
    private const HASH_FALSO = '$2y$12$hHwiBcS098uQs2QE3lEOyOwjcBkPfrewk24uKaXyCpKP9WU5c0IRy';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    /** Comprueba bloqueo por intentos y credenciales. Devuelve el usuario si son correctas. */
    public function autenticar(): User
    {
        $clave = $this->claveLimite();
        $max   = (int) config('smartcampus.login_max_intentos');

        if (RateLimiter::tooManyAttempts($clave, $max)) {
            $min = (int) ceil(RateLimiter::availableIn($clave) / 60);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos fallidos. Espera $min minuto(s) e inténtalo de nuevo.",
            ])->errorBag('login');
        }

        $usuario = User::with('rol')->where('email', $this->input('email'))->first();
        $correcta = Hash::check((string) $this->input('password'), $usuario?->password ?? self::HASH_FALSO);

        if (! $usuario || ! $correcta) {
            RateLimiter::hit($clave, (int) config('smartcampus.login_bloqueo_min') * 60);
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.'])->errorBag('login');
        }

        RateLimiter::clear($clave);

        return $usuario;
    }

    private function claveLimite(): string
    {
        return Str::transliterate($this->input('email') . '|' . $this->ip());
    }
}
