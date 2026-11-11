<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si el administrador desactiva o cierra una cuenta, su sesión abierta deja de servir de inmediato. */
class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->estado !== User::ACTIVA) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('inicio')->with('error', 'Tu cuenta ya no está activa. Contacta al administrador.');
        }

        return $next($request);
    }
}
