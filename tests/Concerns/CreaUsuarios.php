<?php

namespace Tests\Concerns;

use App\Models\Rol;
use App\Models\User;

trait CreaUsuarios
{
    /** Crea un usuario de prueba (contraseña: Clave1234, correo ya confirmado y cuenta activa). */
    protected function usuario(string $rol = 'docente', array $extra = []): User
    {
        $r = Rol::where('slug', $rol)->firstOrFail();

        $u = new User([
            'nombres'  => $extra['nombres'] ?? 'Persona ' . $rol,
            'email'    => $extra['email'] ?? $rol . '@correo.test',
            'password' => 'Clave1234',
        ]);
        unset($extra['nombres'], $extra['email']);

        $u->forceFill(array_merge([
            'rol_id'            => $r->id,
            'estado'            => User::ACTIVA,
            'email_verified_at' => now(),
        ], $extra))->save();

        return $u;
    }

    /** Saca el enlace de la notificación enviada a un usuario. */
    protected function enlaceEnviadoA(User $usuario): string
    {
        $url = null;
        \Illuminate\Support\Facades\Notification::assertSentTo($usuario, \App\Notifications\MensajeCorreo::class, function ($n) use (&$url) {
            $url = $n->botonUrl;
            return true;
        });

        return (string) $url;
    }
}
