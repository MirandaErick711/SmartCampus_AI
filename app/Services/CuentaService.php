<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Support\Correo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Reglas de negocio de las cuentas: invitaciones, relevo de administrador, cierre de sesiones. */
class CuentaService
{
    /**
     * Crea una cuenta "invitada" y envía el enlace para que la persona cree su contraseña.
     * Al usar el enlace, el correo queda confirmado y la cuenta pasa a "activa".
     */
    public function invitar(string $nombres, string $email, Rol $rol, ?string $departamento, User $por): User
    {
        $usuario = new User(['nombres' => $nombres, 'email' => $email, 'departamento' => $departamento]);
        $usuario->forceFill([
            'password'           => Str::random(40),      // nadie la conoce: se reemplaza al aceptar la invitación
            'rol_id'             => $rol->id,
            'estado'             => User::INVITADA,
            'debe_cambiar_clave' => false,
        ])->save();

        Auditoria::registrar(
            $rol->grupo === 'administrador' ? 'Nuevo administrador invitado' : 'Usuario invitado',
            "Se invitó a {$email} como {$rol->nombre}.",
            $por
        );

        $this->enviarInvitacion($usuario->setRelation('rol', $rol), $por);

        return $usuario;
    }

    /** Genera un enlace nuevo (invalida el anterior) y lo envía. Devuelve false si el correo falló. */
    public function enviarInvitacion(User $usuario, User $por): bool
    {
        $token = Password::broker('invitaciones')->createToken($usuario);
        $url   = route('password.reset', ['token' => $token, 'email' => $usuario->email]);
        $horas = (int) round(config('smartcampus.invitacion_min') / 60);
        $rol   = $usuario->rol->nombre;

        $esAdmin = $usuario->esAdministrador();

        return Correo::enviar($usuario, new MensajeCorreo(
            asunto: 'Te invitaron a SmartCampus AI',
            titulo: $esAdmin ? 'Serás el nuevo administrador' : 'Te invitaron a SmartCampus AI',
            saludo: 'Hola, ' . $usuario->nombres . ':',
            parrafos: array_values(array_filter([
                $por->nombres . ' te invitó a SmartCampus AI con el rol de ' . $rol . '.',
                $esAdmin ? 'Al activar tu cuenta, la cuenta del administrador actual se cerrará y tú quedarás a cargo del sistema.' : null,
                "Pulsa el botón para crear tu contraseña y activar tu cuenta. El enlace vence en $horas horas y es de un solo uso.",
            ])),
            botonTexto: 'Activar mi cuenta',
            botonUrl: $url,
            pie: 'Si no esperabas esta invitación, ignora este mensaje.',
        ));
    }

    /**
     * Se ejecuta cuando el nuevo administrador activa su cuenta:
     * las cuentas de administradores anteriores se cierran para siempre (se conserva su historial).
     */
    public function completarRelevo(User $nuevo): void
    {
        $anteriores = User::whereHas('rol', fn ($q) => $q->where('grupo', 'administrador'))
            ->where('id', '!=', $nuevo->id)
            ->whereIn('estado', [User::ACTIVA, User::INACTIVA])
            ->get();

        foreach ($anteriores as $anterior) {
            $anterior->forceFill(['estado' => User::CERRADA, 'debe_cambiar_clave' => false])->save();
            $this->cerrarSesiones($anterior);

            Auditoria::registrar(
                'Cuenta de administrador cerrada',
                "La cuenta de {$anterior->email} se cerró por relevo; asume {$nuevo->email}.",
                $nuevo
            );

            Correo::enviar($anterior, new MensajeCorreo(
                asunto: 'Tu cuenta de administrador fue cerrada · SmartCampus AI',
                titulo: 'Cuenta de administrador cerrada',
                saludo: 'Hola, ' . $anterior->nombres . ':',
                parrafos: [
                    $nuevo->nombres . ' activó su cuenta como nuevo administrador de SmartCampus AI.',
                    'Por eso tu cuenta de administrador quedó cerrada y ya no podrás ingresar. Gracias por tu trabajo.',
                ],
                pie: 'Este es un aviso automático.',
            ));
        }
    }

    /** Cierra todas las sesiones abiertas de un usuario (si las sesiones se guardan en la base de datos). */
    public function cerrarSesiones(User $usuario): void
    {
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
    }
}
