<?php

namespace App\Http\Controllers;

use App\Enums\Prioridad;
use App\Models\Incidencia;
use App\Models\Subcategoria;
use App\Models\User;
use App\Services\IncidenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Acciones de operadores y administrador sobre una incidencia (la ruta ya exige ese rol). */
class GestionIncidenciaController extends Controller
{
    public function __construct(private readonly IncidenciaService $servicio)
    {
    }

    public function evaluar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $this->servicio->evaluar($incidencia, $request->user());

        return back()->with('exito', 'Evaluación iniciada.');
    }

    public function validar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $this->servicio->validarIa($incidencia, $request->user());

        return back()->with('exito', 'Clasificación IA validada.');
    }

    public function reclasificar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $d = $request->validate([
            'categoria_id'    => ['required', 'integer', 'exists:categorias,id'],
            'subcategoria_id' => ['nullable', 'integer', Rule::exists('subcategorias', 'id')->where('categoria_id', $request->input('categoria_id'))],
            'prioridad'       => ['required', Rule::enum(Prioridad::class)],
        ]);

        $this->servicio->reclasificar($incidencia, $request->user(), (int) $d['categoria_id'], $d['subcategoria_id'] ?? null, Prioridad::from($d['prioridad']));

        return back()->with('exito', 'Clasificación actualizada manualmente.');
    }

    public function asignar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $d = $request->validate(['tecnico_id' => ['required', 'integer', 'exists:users,id']], ['tecnico_id.required' => 'Selecciona un técnico disponible.']);

        $this->servicio->asignar($incidencia, $request->user(), User::with('rol')->findOrFail($d['tecnico_id']));

        return back()->with('exito', 'Técnico asignado · estado «Asignada».');
    }

    public function cerrar(Request $request, Incidencia $incidencia): RedirectResponse
    {
        $this->servicio->cerrar($incidencia, $request->user());

        return back()->with('exito', 'Ticket cerrado definitivamente.');
    }
}
