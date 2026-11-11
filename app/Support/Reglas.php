<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Reglas de validación compartidas (un solo lugar para cambiarlas). */
class Reglas
{
    /** Contraseña: mínimo 8, con letras y números. */
    public static function clave(): Password
    {
        return Password::min((int) config('smartcampus.clave_min', 8))->letters()->numbers();
    }

    /** Correo: formato válido y, si se configuró, solo de los dominios permitidos. */
    public static function correo(): array
    {
        return [
            'required', 'string', 'max:190', 'email:rfc',
            function (string $atributo, mixed $valor, \Closure $fallar) {
                $permitidos = config('smartcampus.dominios_correo', []);
                if ($permitidos && ! in_array(Str::after(mb_strtolower((string) $valor), '@'), $permitidos, true)) {
                    $fallar('Solo se permiten correos de: ' . implode(', ', $permitidos) . '.');
                }
            },
        ];
    }

    /** Nombre de persona: letras, espacios, punto, apóstrofo y guion (bloquea etiquetas HTML y scripts). */
    public const NOMBRE = '/^[\pL\pM\s.\'’\-]+$/u';

    /** Texto corto tipo departamento: letras, números y signos comunes. */
    public const TEXTO_CORTO = '/^[\pL\pM\pN\s.,\'’\-\/&()]+$/u';
}
