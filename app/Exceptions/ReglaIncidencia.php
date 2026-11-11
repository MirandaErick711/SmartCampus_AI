<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/** Se lanza cuando una acción no está permitida en el estado actual de la incidencia. Se muestra como aviso rojo. */
class ReglaIncidencia extends RuntimeException
{
    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
