<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Añade cabeceras que frenan XSS, clickjacking y filtración de datos del navegador. */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('smartcampus.csp')) {
            // Solo se permite código propio y las dos librerías externas que usa el diseño (íconos y gráficos).
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' https://unpkg.com https://cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
