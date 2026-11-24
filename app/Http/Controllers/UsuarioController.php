<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use App\Services\CuentaService;
use App\Support\Correo;
use App\Support\Reglas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/** "Usuarios y roles" (solo administrador). */
class UsuarioController extends Controller
{
    public function __construct(private CuentaService $cuentas)
    {
    }

    public function index(Request $request)
    {
        $usuarios = User::with('rol')->where('estado', '!=', User::CERRADA)->orderBy('nombres')->get();

        return view('usuarios.index', [
            'usuarios'   => $usuarios,
            'pendientes' => $usuarios->where('estado', User::PENDIENTE),
            'relevo'     => $usuarios->first(fn (User $u) => $u->estado === User::INVITADA && $u->esAdministrador()),
            'roles'      => Rol::where('slug', '!=', 'administrador')->orderBy('id')->get(),
            'porRol'     => Rol::withCount(['usuarios as activos' => fn ($q) => $q->where('estado', User::ACTIVA)])->orderBy('id')->get(),
        ]);
    }

    /** Invita a una persona: recibe un correo para crear su contraseña. */
    public function invitar(Request $request): RedirectResponse
    {
        $request->merge($this->normalizar($request));

        $datos = $request->validateWithBag('invitar', [
            'nombres'      => ['required', 'string', 'min:3', 'max:120', 'regex:' . Reglas::NOMBRE],
            'email'        => [...Reglas::correo(), Rule::unique('users', 'email')],
            'rol_id'       => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($q) => $q->where('slug', '!=', 'administrador'))],
            'departamento' => ['nullable', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO],
        ]);

        $rol = Rol::findOrFail($datos['rol_id']);
        $this->cuentas->invitar($datos['nombres'], $datos['email'], $rol, $datos['departamento'] ?? null, $request->user());

        return back()->with('exito', "Invitación enviada a {$datos['email']}.");
    }

    /**
     * Relevo: el administrador actual invita a su reemplazo. Cuando la persona activa su cuenta,
     * la cuenta del administrador actual se cierra para siempre.
     */
    public function relevo(Request $request): RedirectResponse
    {
        $request->merge($this->normalizar($request));

        $datos = $request->validateWithBag('relevo', [
            'nombres'      => ['required', 'string', 'min:3', 'max:120', 'regex:' . Reglas::NOMBRE],
            'email'        => [...Reglas::correo(), Rule::unique('users', 'email')],
            'clave_actual' => ['required', 'string', 'current_password'],   // confirma que es el administrador real
        ]);

        // Una sola invitación de relevo a la vez: la anterior (sin activar) se descarta.
        User::where('estado', User::INVITADA)->whereHas('rol', fn ($q) => $q->where('grupo', 'administrador'))->delete();

        $rol = Rol::where('slug', 'administrador')->firstOrFail();
        $this->cuentas->invitar($datos['nombres'], $datos['email'], $rol, null, $request->user());

        return back()->with('exito', "Invitación enviada a {$datos['email']}. Cuando active su cuenta, la tuya se cerrará.");
    }

    public function aprobar(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->estado === User::PENDIENTE, 422, 'La cuenta no está pendiente.');

        $usuario->forceFill(['estado' => User::ACTIVA])->save();
        Auditoria::registrar('Cuenta aprobada', "Se aprobó la cuenta de {$usuario->email}.");

        Correo::enviar($usuario, new MensajeCorreo(
            asunto: 'Tu cuenta fue aprobada · SmartCampus AI',
            titulo: 'Tu cuenta fue aprobada',
            saludo: 'Hola, ' . $usuario->nombres . ':',
            parrafos: [
                'El administrador aprobó tu cuenta de SmartCampus AI.',
                $usuario->hasVerifiedEmail()
                    ? 'Ya puedes iniciar sesión.'
                    : 'Solo falta que confirmes tu correo con el enlace que te enviamos al registrarte.',
            ],
            botonTexto: 'Ir a SmartCampus AI',
            botonUrl: route('inicio'),
            pie: 'Este es un aviso automático.',
        ));

        return back()->with('exito', "Cuenta de {$usuario->nombres} aprobada.");
    }

    /** Elimina una solicitud pendiente o cancela una invitación sin activar. */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        abort_unless(in_array($usuario->estado, [User::PENDIENTE, User::INVITADA], true), 422, 'Solo se pueden eliminar solicitudes o invitaciones.');

        $era = $usuario->estado;
        $correo = $usuario->email;
        $nombre = $usuario->nombres;
        $usuario->delete();

        Auditoria::registrar($era === User::PENDIENTE ? 'Solicitud rechazada' : 'Invitación cancelada', "Cuenta de {$correo}.");

        if ($era === User::PENDIENTE) {
            Correo::enviar($correo, new MensajeCorreo(
                asunto: 'Tu solicitud no fue aprobada · SmartCampus AI',
                titulo: 'Solicitud no aprobada',
                saludo: 'Hola, ' . $nombre . ':',
                parrafos: ['El administrador no aprobó tu solicitud de cuenta en SmartCampus AI. Si crees que es un error, comunícate con él.'],
                pie: 'Este es un aviso automático.',
            ));
        }

        return back()->with('exito', $era === User::PENDIENTE ? 'Solicitud rechazada.' : 'Invitación cancelada.');
    }

    public function reenviarInvitacion(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->estado === User::INVITADA, 422);

        $ok = $this->cuentas->enviarInvitacion($usuario->load('rol'), $request->user());

        return back()->with($ok ? 'exito' : 'error', $ok ? 'Invitación reenviada.' : 'No pudimos enviar el correo. Inténtalo de nuevo.');
    }

    /** Cambia el rol (no se puede dar el rol de administrador: eso solo se hace con el relevo). */
    public function rol(Request $request, User $usuario): RedirectResponse
    {
        $this->asegurarGestionable($request, $usuario);

        $datos = $request->validate([
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($q) => $q->where('slug', '!=', 'administrador'))],
        ]);

        $rol = Rol::findOrFail($datos['rol_id']);
        $usuario->forceFill(['rol_id' => $rol->id])->save();
        Auditoria::registrar('Rol cambiado', "{$usuario->email} ahora es {$rol->nombre}.");

        return back()->with('exito', "{$usuario->nombres} ahora es {$rol->nombre}.");
    }

    /** Activa o desactiva una cuenta. */
    public function estado(Request $request, User $usuario): RedirectResponse
    {
        $this->asegurarGestionable($request, $usuario);
        abort_unless(in_array($usuario->estado, [User::ACTIVA, User::INACTIVA], true), 422);

        $nuevo = $usuario->estado === User::ACTIVA ? User::INACTIVA : User::ACTIVA;
        $usuario->forceFill(['estado' => $nuevo])->save();

        if ($nuevo === User::INACTIVA) {
            $this->cuentas->cerrarSesiones($usuario);
        }
        Auditoria::registrar($nuevo === User::ACTIVA ? 'Cuenta activada' : 'Cuenta desactivada', "Cuenta de {$usuario->email}.");

        return back()->with('exito', $nuevo === User::ACTIVA ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }

    /** Ayuda a recuperar una cuenta: envía al usuario el enlace para elegir una contraseña nueva. */
    public function restablecer(Request $request, User $usuario): RedirectResponse
    {
        abort_unless($usuario->estado === User::ACTIVA, 422, 'Solo se puede ayudar a cuentas activas.');

        $estado = Password::sendResetLink(['email' => $usuario->email]);
        Auditoria::registrar('Recuperación asistida', "El administrador envió un enlace de recuperación a {$usuario->email}.");

        return back()->with(
            $estado === Password::RESET_LINK_SENT ? 'exito' : 'error',
            $estado === Password::RESET_LINK_SENT
                ? "Enviamos un enlace de recuperación a {$usuario->email}."
                : 'Ya se envió un enlace hace poco. Espera un minuto e inténtalo de nuevo.'
        );
    }

    /** No se puede modificar la propia cuenta ni la de un administrador desde esta lista. */
    private function asegurarGestionable(Request $request, User $usuario): void
    {
        abort_if($usuario->is($request->user()), 422, 'No puedes modificar tu propia cuenta desde aquí.');
        abort_if($usuario->load('rol')->esAdministrador(), 422, 'La cuenta del administrador solo cambia mediante el relevo.');
    }

    private function normalizar(Request $request): array
    {
        return [
            'nombres'      => trim(preg_replace('/\s+/u', ' ', (string) $request->input('nombres'))),
            'email'        => mb_strtolower(trim((string) $request->input('email'))),
            'departamento' => trim((string) $request->input('departamento')) ?: null,
        ];
    }
}
