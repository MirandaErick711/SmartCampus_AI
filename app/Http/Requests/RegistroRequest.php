<?php

namespace App\Http\Requests;

use App\Support\Reglas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistroRequest extends FormRequest
{
    /** Los errores se muestran dentro del cuadro de "Registrarse". */
    protected $errorBag = 'registro';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('nombres'))),
            'email'   => mb_strtolower(trim((string) $this->input('email'))),
            'departamento' => trim((string) $this->input('departamento')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombres'      => ['required', 'string', 'min:3', 'max:120', 'regex:' . Reglas::NOMBRE],
            'email'        => [...Reglas::correo(), Rule::unique('users', 'email')],
            // Solo roles que se pueden elegir al registrarse (el de administrador no está entre ellos)
            'rol'          => ['required', 'string', Rule::exists('roles', 'slug')->where('registro_publico', true)],
            'departamento' => ['nullable', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO],
            'password'     => ['required', 'string', 'confirmed', Reglas::clave()],
        ];
    }
}
