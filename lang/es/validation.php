<?php

return [
    'accepted'         => 'Debes aceptar :attribute.',
    'array'            => 'El campo :attribute debe ser una lista.',
    'boolean'          => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed'        => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'different'        => 'El campo :attribute y :other deben ser diferentes.',
    'email'            => 'El campo :attribute debe ser un correo válido.',
    'exists'           => 'El valor seleccionado en :attribute no es válido.',
    'in'               => 'El valor seleccionado en :attribute no es válido.',
    'integer'          => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array'   => 'El campo :attribute no puede tener más de :max elementos.',
        'file'    => 'El archivo :attribute no puede superar :max KB.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string'  => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array'   => 'El campo :attribute debe tener al menos :min elementos.',
        'file'    => 'El archivo :attribute debe pesar al menos :min KB.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string'  => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'not_in'           => 'El nuevo correo debe ser distinto del actual.',
    'nullable'         => 'El campo :attribute puede quedar vacío.',
    'regex'            => 'El formato de :attribute no es válido (hay caracteres no permitidos).',
    'required'         => 'El campo :attribute es obligatorio.',
    'same'             => 'El campo :attribute y :other deben coincidir.',
    'string'           => 'El campo :attribute debe ser texto.',
    'unique'           => 'Ya existe una cuenta con ese :attribute.',

    // Reglas de contraseña (Password::min(8)->letters()->numbers())
    'password' => [
        'letters'       => 'La :attribute debe incluir al menos una letra.',
        'mixed'         => 'La :attribute debe incluir mayúsculas y minúsculas.',
        'numbers'       => 'La :attribute debe incluir al menos un número.',
        'symbols'       => 'La :attribute debe incluir al menos un símbolo.',
        'uncompromised' => 'Esa :attribute apareció en filtraciones de datos. Elige otra.',
    ],

    'custom' => [],

    // Nombres amigables de los campos
    'attributes' => [
        'nombres'               => 'nombre',
        'email'                 => 'correo',
        'password'              => 'contraseña',
        'password_confirmation' => 'confirmación de la contraseña',
        'clave_actual'          => 'contraseña actual',
        'rol'                   => 'rol',
        'rol_id'                => 'rol',
        'departamento'          => 'departamento',
        'token'                 => 'enlace',
    ],
];
