<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorreoRequest;
use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Services\CuentaService;
use App\Support\Correo;
use App\Support\Reglas;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecuperarController extends Controller
{
    /** Paso 1: la persona escribe su correo. La respuesta es SIEMPRE la misma (no revela qué correos existen). */
    public function solicitar(CorreoRequest $request): RedirectResponse
    {
        $email = $request->validated()['email'];

        $usuario = User::where('email', $email)->first();
        if ($usuario && $usuario->estado === User::ACTIVA && $usuario->hasVerifiedEmail()) {
            // Se envía después de responder, para que la rapidez no delate si el correo existe.
            dispatch(function () use ($email) {
                Password::sendResetLink(['email' => $email]);
            })->afterResponse();

            Auditoria::registrar('Recuperación solicitada', "Se pidió un enlace de recuperación para {$email}.", $usuario);
        }

        return redirect()->route('inicio')
            ->with('exito', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. '
                . 'Revisa tu bandeja de entrada y la carpeta de spam. El enlace vence en '
                . (int) config('auth.passwords.users.expire') . ' minutos.')
            ->with('modal', 'login');
    }

    /** Paso 2: página a la que lleva el enlace del correo. */
    public function formulario(Request $request, string $token)
    {
        return view('auth.restablecer', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /** Paso 3: guardar la contraseña nueva. */
    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'token'    => ['required', 'string', 'max:200'],
            'email'    => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', Reglas::clave()],
        ]);
        $datos['email'] = mb_strtolower(trim($datos['email']));

        $usuario = User::where('email', $datos['email'])->first();

        // Solo las cuentas activas (recuperación) o invitadas (primera activación) pueden usar un enlace.
        if ($usuario && ! in_array($usuario->estado, [User::ACTIVA, User::INVITADA], true)) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ya venció. Solicita uno nuevo.']);
        }

        // Las invitaciones usan un broker con vencimiento más largo que la recuperación normal.
        $broker = $usuario?->estado === User::INVITADA ? 'invitaciones' : 'users';

        $estado = Password::broker($broker)->reset(
            ['email' => $datos['email'], 'password' => $datos['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $datos['token']],
            function (User $usuario, string $clave) {
                $activando = $usuario->estado === User::INVITADA;

                DB::transaction(function () use ($usuario, $clave, $activando) {
                    $usuario->forceFill([
                        'password'           => $clave,                 // se cifra solo (cast "hashed")
                        'remember_token'     => Str::random(60),
                        'debe_cambiar_clave' => false,
                        'estado'             => User::ACTIVA,
                    ])->save();

                    // Entrar por el enlace del correo demuestra que el correo es suyo.
                    if (! $usuario->hasVerifiedEmail()) {
                        $usuario->markEmailAsVerified();
                    }

                    if ($activando && $usuario->esAdministrador()) {
                        app(CuentaService::class)->completarRelevo($usuario);
                    }
                });

                // Cualquier sesión abierta con la contraseña anterior deja de servir.
                app(CuentaService::class)->cerrarSesiones($usuario);

                Auditoria::registrar(
                    $activando ? 'Cuenta activada por invitación' : 'Contraseña restablecida',
                    $activando ? 'La persona activó su cuenta con el enlace de invitación.' : 'Se restableció la contraseña con un enlace de recuperación.',
                    $usuario
                );

                event(new PasswordReset($usuario));

                Correo::enviar($usuario, new MensajeCorreo(
                    asunto: 'Tu contraseña fue cambiada · SmartCampus AI',
                    titulo: 'Tu contraseña fue cambiada',
                    saludo: 'Hola, ' . $usuario->nombres . ':',
                    parrafos: [
                        'La contraseña de tu cuenta de SmartCampus AI se acaba de establecer.',
                        'Si no fuiste tú, comunícate de inmediato con el administrador del sistema.',
                    ],
                    pie: 'Este es un aviso automático de seguridad.',
                ));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            // Mensaje genérico: no se distingue entre token malo, vencido o correo inexistente.
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ya venció. Solicita uno nuevo.']);
        }

        return redirect()->route('inicio')
            ->with('exito', 'Contraseña guardada correctamente. Ya puedes iniciar sesión.')
            ->with('modal', 'login');
    }
}
