<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Segundo "broker" de contraseñas con vencimiento más largo, solo para invitaciones
        // (nuevo usuario o nuevo administrador). Usa la misma tabla password_reset_tokens.
        config([
            'auth.passwords.invitaciones' => [
                'provider' => 'users',
                'table'    => 'password_reset_tokens',
                'expire'   => (int) config('smartcampus.invitacion_min', 4320),
                'throttle' => 60,
            ],
        ]);
    }

    public function boot(): void
    {
        //
    }
}
