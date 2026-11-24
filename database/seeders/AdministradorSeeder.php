<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdministradorSeeder extends Seeder
{
    /**
     * Crea el administrador inicial (solo si todavía no existe ningún administrador).
     * Usa SC_ADMIN_EMAIL, SC_ADMIN_NOMBRE y SC_ADMIN_CLAVE del archivo .env.
     */
    public function run(): void
    {
        $rol = Rol::where('slug', 'administrador')->firstOrFail();

        if (User::where('rol_id', $rol->id)->whereIn('estado', [User::ACTIVA, User::INACTIVA])->exists()) {
            return;
        }

        $email = strtolower(trim((string) config('smartcampus.admin_email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'Define SC_ADMIN_EMAIL en el archivo .env con un correo REAL (recibirá los enlaces de recuperación) '
                . 'y vuelve a ejecutar: php artisan migrate:fresh --seed'
            );
        }

        $admin = new User();
        $admin->forceFill([
            'nombres'            => config('smartcampus.admin_nombre'),
            'email'              => $email,
            'email_verified_at'  => now(),
            'password'           => config('smartcampus.admin_clave'),   // se cifra solo (cast "hashed")
            'rol_id'             => $rol->id,
            'estado'             => User::ACTIVA,
            'debe_cambiar_clave' => true,
        ])->save();
    }
}
