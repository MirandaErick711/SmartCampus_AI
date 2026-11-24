<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    private const CUADROS = ['login', 'registro', 'olvide', 'reenviar'];

    public function __invoke(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('panel');
        }

        // ¿Qué cuadro (modal) debe abrirse al cargar? El que tuvo errores, el pedido por la sesión o por la URL.
        $abrir   = session('modal');
        $errores = $request->session()->get('errors');
        if ($errores) {
            foreach (self::CUADROS as $cuadro) {
                if ($errores->getBag($cuadro)->any()) {
                    $abrir = $cuadro;
                    break;
                }
            }
        }
        if (! $abrir && in_array($request->query('acceso'), ['login', 'registro'], true)) {
            $abrir = $request->query('acceso');
        }

        return view('landing', [
            'abrir' => $abrir,
            'roles' => Rol::where('registro_publico', true)->orderBy('id')->get(),
        ]);
    }
}
