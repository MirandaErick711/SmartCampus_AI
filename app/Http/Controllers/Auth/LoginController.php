<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        $usuario = $request->autenticar();   // ya validó correo + contraseña (y el bloqueo por intentos)

        // La contraseña es correcta; ahora se revisa si la cuenta puede entrar.
        $problema = match (true) {
            ! $usuario->hasVerifiedEmail()        => 'Debes confirmar tu correo. Revisa tu bandeja de entrada (y spam) o solicita un nuevo enlace.',
            $usuario->estado === User::PENDIENTE  => 'Tu cuenta está pendiente de aprobación por el administrador.',
            $usuario->estado === User::INVITADA   => 'Tu cuenta aún no está activada. Usa el enlace de invitación que te enviamos por correo.',
            $usuario->estado === User::INACTIVA   => 'Tu cuenta está desactivada. Contacta al administrador.',
            $usuario->estado === User::CERRADA    => 'Esta cuenta fue cerrada.',
            default                               => null,
        };

        if ($problema) {
            throw ValidationException::withMessages(['email' => $problema])->errorBag('login');
        }

        Auth::login($usuario, $request->boolean('recordar'));
        $request->session()->regenerate();   // evita la fijación de sesión

        $usuario->forceFill(['ultimo_acceso' => now()])->save();
        Auditoria::registrar('Inicio de sesión', 'Ingreso al sistema.', $usuario);

        return redirect()->intended(route('panel'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auditoria::registrar('Cierre de sesión', null, $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio')->with('exito', 'Sesión cerrada.');
    }
}
