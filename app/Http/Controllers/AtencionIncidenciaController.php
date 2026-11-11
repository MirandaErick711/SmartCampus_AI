<?php

namespace App\Http\Controllers;

use App\Models\Incidencia;
use App\Services\IncidenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Acciones del técnico sobre las incidencias que tiene asignadas (la ruta exige el rol; el servicio, la asignación). */
class AtencionIncidenciaController extends Controller
{
    public function __construct(private readonly IncidenciaService $servicio)
    {
    }

    public function iniciar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $this->servicio->iniciar($incidencia, $request->user());

        return back()->with('exito', 'Atención iniciada.');
    }

    public function resolver(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $request->merge(['informe' => trim(strip_tags((string) $request->input('informe')))]);
        $d = $request->validate([
            'informe' => ['required', 'string', 'min:10', 'max:4000'],
            'foto'    => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'informe.required' => 'Escribe el informe técnico de reparación.',
            'informe.min'      => 'El informe técnico debe tener al menos 10 caracteres.',
        ]);

        $this->servicio->resolver($incidencia, $request->user(), $d['informe'], $request->file('foto'));

        return back()->with('exito', 'Incidencia marcada como Resuelta.');
    }
}
