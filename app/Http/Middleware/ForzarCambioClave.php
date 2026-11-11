<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Quien entró con una contraseña temporal debe cambiarla antes de usar el sistema. */
class ForzarCambioClave
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_clave) {
            return redirect()->route('cuenta.edit');
        }

        return $next($request);
    }
}
