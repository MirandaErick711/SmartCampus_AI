<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Incidencia;
use App\Models\User;
use App\Services\Estadisticas;
use App\Services\IncidenciaService;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function __invoke(Request $request, Estadisticas $estadisticas, IncidenciaService $incidencias)
    {
        $usuario = $request->user()->load('rol');

        $resumen = null;
        if ($usuario->esAdministrador()) {
            $resumen = [
                'activas'     => User::where('estado', User::ACTIVA)->count(),
                'pendientes'  => User::where('estado', User::PENDIENTE)->count(),
                'invitadas'   => User::where('estado', User::INVITADA)->count(),
                'inactivas'   => User::where('estado', User::INACTIVA)->count(),
            ];
        }

        $datos = $estadisticas->panel($usuario);

        $visibles = fn () => Incidencia::query()->visiblePara($usuario);
        $recientes = $visibles()->with(['ubicacion.pabellon'])->latest('incidencias.updated_at')->limit(5)->get();
        $criticas = $visibles()->with(['ubicacion.pabellon'])
            ->where('incidencias.prioridad', Prioridad::Critica->value)
            ->whereNotIn('incidencias.estado', [EstadoIncidencia::Resuelta->value, EstadoIncidencia::Cerrada->value])
            ->latest('incidencias.updated_at')->limit(5)->get();

        // Carga de trabajo de los técnicos (no se muestra a quien solo reporta)
        $tecnicos = $usuario->grupo() === 'reportante' ? collect() : $incidencias->tecnicosDisponibles();

        return view('panel.index', compact('usuario', 'resumen', 'datos', 'recientes', 'criticas', 'tecnicos'));
    }
}
