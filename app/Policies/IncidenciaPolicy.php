<?php

namespace App\Policies;

use App\Models\Incidencia;
use App\Models\User;

class IncidenciaPolicy
{
    /** Quién puede ver una incidencia (y sus fotos): quien la reportó, el técnico asignado, operadores y administrador. */
    public function ver(User $u, Incidencia $inc): bool
    {
        return match ($u->grupo()) {
            'operador', 'administrador' => true,
            'reportante'                => $inc->reportante_id === $u->id,
            'tecnico'                   => $inc->tecnico_id === $u->id,
            default                     => false,
        };
    }
}
