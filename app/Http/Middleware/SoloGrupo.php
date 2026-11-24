<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso en rutas: ->middleware('grupo:administrador')  o  'grupo:operador,administrador'. */
class SoloGrupo
{
    public function handle(Request $request, Closure $next, string ...$grupos): Response
    {
        $grupo = (string) $request->user()?->rol?->grupo;

        abort_unless(in_array($grupo, $grupos, true), 403, 'No tienes permiso para esta sección.');

        return $next($request);
    }
}
