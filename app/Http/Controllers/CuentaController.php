<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Support\Correo;
use App\Support\Reglas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** "Mi cuenta": datos personales, cambio de contraseña y cambio de correo (con confirmación). */
class CuentaController extends Controller
{
    public function edit(Request $request)
    {
        return view('cuenta.edit', ['usuario' => $request->user()->load('rol')]);
    }

    public function perfil(Request $request): RedirectResponse
    {
        $request->merge([
            'nombres'      => trim(preg_replace('/\s+/u', ' ', (string) $request->input('nombres'))),
            'departamento' => trim((string) $request->input('departamento')) ?: null,
        ]);

        $datos = $request->validateWithBag('perfil', [
            'nombres'      => ['required', 'string', 'min:3', 'max:120', 'regex:' . Reglas::NOMBRE],
            'departamento' => ['nullable', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO],
        ]);

        $request->user()->update($datos);
        Auditoria::registrar('Perfil actualizado', 'Cambió su nombre o departamento.');

        return back()->with('exito', 'Datos actualizados.');
    }

    public function clave(Request $request): RedirectResponse
    {
        $request->validateWithBag('clave', [
            'clave_actual' => ['required', 'string', 'current_password'],
            'password'     => ['required', 'string', 'confirmed', 'different:clave_actual', Reglas::clave()],
        ]);

        $usuario = $request->user();
        $usuario->forceFill(['password' => $request->input('password'), 'debe_cambiar_clave' => false])->save();
        Auditoria::registrar('Contraseña cambiada', 'Cambió su contraseña desde Mi cuenta.');

        Correo::enviar($usuario, new MensajeCorreo(
            asunto: 'Tu contraseña fue cambiada · SmartCampus AI',
            titulo: 'Tu contraseña fue cambiada',
            saludo: 'Hola, ' . $usuario->nombres . ':',
            parrafos: [
                'La contraseña de tu cuenta de SmartCampus AI se acaba de cambiar.',
                'Si no fuiste tú, comunícate de inmediato con el administrador del sistema.',
            ],
            pie: 'Este es un aviso automático de seguridad.',
        ));

        return redirect()->route('panel')->with('exito', 'Contraseña actualizada correctamente.');
    }

    /** Paso 1 del cambio de correo: se guarda el nuevo y se le envía un enlace de confirmación. */
    public function correo(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $datos = $request->validateWithBag('correo', [
            'clave_actual' => ['required', 'string', 'current_password'],
            'email'        => [...Reglas::correo(), Rule::unique('users', 'email'), Rule::notIn([$request->user()->email])],
        ]);

        $usuario = $request->user();
        $usuario->forceFill(['email_pendiente' => $datos['email']])->save();

        $url = URL::temporarySignedRoute(
            'cuenta.correo.confirmar',
            now()->addMinutes(config('smartcampus.verificacion_min')),
            ['id' => $usuario->id, 'hash' => sha1($datos['email'])]
        );

        $enviado = Correo::enviar($datos['email'], new MensajeCorreo(
            asunto: 'Confirma tu nuevo correo · SmartCampus AI',
            titulo: 'Confirma tu nuevo correo',
            saludo: 'Hola, ' . $usuario->nombres . ':',
            parrafos: [
                'Pediste usar este correo en tu cuenta de SmartCampus AI.',
                'Pulsa el botón para confirmarlo. Mientras no lo hagas, seguirás entrando con tu correo actual.',
            ],
            botonTexto: 'Confirmar nuevo correo',
            botonUrl: $url,
            pie: 'Si no fuiste tú, ignora este mensaje.',
        ));

        Auditoria::registrar('Cambio de correo solicitado', "Pidió cambiar su correo a {$datos['email']}.");

        return back()->with($enviado ? 'exito' : 'error', $enviado
            ? "Te enviamos un enlace de confirmación a {$datos['email']}. Tu correo cambiará cuando lo confirmes."
            : 'No pudimos enviar el correo de confirmación. Inténtalo de nuevo en unos minutos.');
    }

    /** Paso 2: enlace firmado que llega al nuevo correo. */
    public function confirmarCorreo(int $id, string $hash): RedirectResponse
    {
        $usuario = User::findOrFail($id);
        $nuevo   = (string) $usuario->email_pendiente;

        abort_unless($nuevo !== '' && hash_equals(sha1($nuevo), $hash), 403);

        $destino = Auth::check() ? 'cuenta.edit' : 'inicio';

        if (User::where('email', $nuevo)->where('id', '!=', $usuario->id)->exists()) {
            return redirect()->route($destino)->with('error', 'Ese correo ya fue tomado por otra cuenta.');
        }

        $anterior = $usuario->email;
        $usuario->forceFill(['email' => $nuevo, 'email_pendiente' => null, 'email_verified_at' => now()])->save();
        Auditoria::registrar('Correo cambiado', "Cambió su correo de {$anterior} a {$nuevo}.", $usuario);

        return redirect()->route($destino)->with('exito', 'Tu correo se actualizó. Desde ahora ingresa con ' . $nuevo . '.');
    }
}
