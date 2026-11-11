<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorreoRequest;
use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Support\Correo;
use Illuminate\Http\RedirectResponse;

class CorreoController extends Controller
{
    /** Enlace del correo de confirmación (firmado y con vencimiento; funciona sin haber iniciado sesión). */
    public function verificar(int $id, string $hash): RedirectResponse
    {
        $usuario = User::findOrFail($id);

        // El hash va atado al correo actual: si el correo cambió, el enlace viejo deja de servir.
        abort_unless(hash_equals(sha1($usuario->email), $hash), 403);

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
            Auditoria::registrar('Correo confirmado', "{$usuario->email} confirmó su correo.", $usuario);
        }

        $mensaje = 'Correo confirmado.';
        if ($usuario->estado === User::PENDIENTE) {
            $mensaje .= ' Tu cuenta está pendiente de aprobación por el administrador; te avisaremos por correo.';
        } elseif ($usuario->estado === User::ACTIVA) {
            $mensaje .= ' Ya puedes iniciar sesión.';
        }

        return redirect()->route('inicio')->with('exito', $mensaje)->with('modal', 'login');
    }

    /** "Reenviar confirmación": siempre responde lo mismo, exista o no el correo. */
    public function reenviar(CorreoRequest $request): RedirectResponse
    {
        $email = $request->validated()['email'];

        $usuario = User::where('email', $email)->first();
        if ($usuario && ! $usuario->hasVerifiedEmail() && in_array($usuario->estado, [User::ACTIVA, User::PENDIENTE], true)) {
            Correo::enviar($usuario, new MensajeCorreo(
                asunto: 'Confirma tu correo · SmartCampus AI',
                titulo: 'Confirma tu correo',
                saludo: 'Hola, ' . $usuario->nombres . ':',
                parrafos: [
                    'Pulsa el botón para confirmar que este correo es tuyo. El enlace vence en '
                        . (int) round(config('smartcampus.verificacion_min') / 60) . ' horas.',
                ],
                botonTexto: 'Confirmar mi correo',
                botonUrl: $usuario->urlConfirmacionCorreo(),
                pie: 'Si no creaste esta cuenta, ignora este mensaje.',
            ));
        }

        return redirect()->route('inicio')
            ->with('exito', 'Si el correo está registrado y aún no fue confirmado, te enviamos un nuevo enlace.')
            ->with('modal', 'login');
    }
}
