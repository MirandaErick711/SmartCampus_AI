<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pide solo un correo. Sirve para "¿Olvidaste tu contraseña?" (bolsa "olvide")
 * y para "Reenviar confirmación" (bolsa "reenviar"), según la ruta.
 */
class CorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Nombre de la bolsa de errores = cuadro que se vuelve a abrir si hay un error
        $this->errorBag = $this->routeIs('password.email') ? 'olvide' : 'reenviar';

        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:190']];
    }
}
