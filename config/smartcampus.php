<?php

/*
|--------------------------------------------------------------------------
| SmartCampus AI · Configuración propia
|--------------------------------------------------------------------------
| Todo se puede cambiar desde el archivo .env (no hace falta tocar este archivo).
*/

return [

    // Administrador inicial (lo crea: php artisan db:seed). El correo DEBE ser real.
    'admin_email'  => env('SC_ADMIN_EMAIL'),
    'admin_nombre' => env('SC_ADMIN_NOMBRE', 'Administrador SmartCampus'),
    'admin_clave'  => env('SC_ADMIN_CLAVE', 'Admin2026*'),   // temporal: se obliga a cambiarla al entrar

    // Minutos de validez del enlace de confirmación de correo
    'verificacion_min' => (int) env('SC_VERIFICACION_MIN', 1440),

    // Minutos de validez de la invitación (nuevo usuario / nuevo administrador)
    'invitacion_min' => (int) env('SC_INVITACION_MIN', 4320),

    // Largo mínimo de contraseña (además exige letras y números)
    'clave_min' => 8,

    // Intentos fallidos de ingreso permitidos y minutos de bloqueo
    'login_max_intentos' => 5,
    'login_bloqueo_min'  => 15,

    // Dominios de correo permitidos, separados por coma. Vacío = cualquier correo.
    // Ejemplo: SC_DOMINIOS_CORREO=gmail.com,miuniversidad.edu.pe
    'dominios_correo' => array_values(array_filter(array_map(
        fn ($d) => strtolower(trim($d)),
        explode(',', (string) env('SC_DOMINIOS_CORREO', ''))
    ))),

    // Política de seguridad de contenido (CSP). Pon SC_CSP=false solo si necesitas depurar.
    'csp' => (bool) env('SC_CSP', true),
];
