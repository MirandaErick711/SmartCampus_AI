<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroRequest;
use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Support\Correo;
use Illuminate\Http\RedirectResponse;

class RegistroController extends Controller
{
    public function store(RegistroRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $rol   = Rol::where('slug', $datos['rol'])->where('registro_publico', true)->firstOrFail();

        $usuario = new User([
            'nombres'      => $datos['nombres'],
            'email'        => $datos['email'],
            'password'     => $datos['password'],       // se cifra solo (cast "hashed")
            'departamento' => $datos['departamento'] ?? null,
        ]);
        // El rol y el estado los decide el servidor, nunca el formulario.
        $usuario->forceFill([
            'rol_id' => $rol->id,
            'estado' => $rol->requiere_aprobacion ? User::PENDIENTE : User::ACTIVA,
        ])->save();

        Auditoria::registrar('Registro de cuenta', "Se registró {$usuario->email} como {$rol->nombre}.", $usuario);

        $enviado = Correo::enviar($usuario, new MensajeCorreo(
            asunto: 'Confirma tu correo · SmartCampus AI',
            titulo: 'Confirma tu correo',
            saludo: 'Hola, ' . $usuario->nombres . ':',
            parrafos: [
                'Gracias por registrarte en SmartCampus AI como ' . $rol->nombre . '.',
                'Confirma que este correo es tuyo pulsando el botón. El enlace vence en '
                    . (int) round(config('smartcampus.verificacion_min') / 60) . ' horas.',
            ],
            botonTexto: 'Confirmar mi correo',
            botonUrl: $usuario->urlConfirmacionCorreo(),
            pie: 'Si no creaste esta cuenta, ignora este mensaje.',
        ));

        $mensaje = $enviado
            ? "Cuenta creada. Te enviamos un enlace de confirmación a {$usuario->email}."
            : 'Cuenta creada, pero no pudimos enviar el correo de confirmación. Usa "Reenviar confirmación" en el inicio de sesión.';

        if ($rol->requiere_aprobacion) {
            $mensaje .= ' Después de confirmar tu correo, el administrador deberá aprobar tu cuenta.';
        }

        return redirect()->route('inicio')->with('exito', $mensaje)->with('modal', 'login');
    }
}
