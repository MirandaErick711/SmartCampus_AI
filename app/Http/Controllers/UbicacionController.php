<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Campus;
use App\Models\Pabellon;
use App\Models\Ubicacion;
use App\Services\MotorIA;
use App\Support\Reglas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogo de ubicaciones (campus, pabellones, aulas y laboratorios). Solo administrador. */
class UbicacionController extends Controller
{
    public function index(MotorIA $motor)
    {
        return view('ubicaciones.index', [
            'campus'   => Campus::with('pabellones.ubicaciones')->orderBy('nombre')->get(),
            'palabras' => $motor->palabrasPorCategoria(),
        ]);
    }

    public function campus(Request $request): RedirectResponse
    {
        $d = $request->validateWithBag('campus', [
            'nombre' => ['required', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO, 'unique:campus,nombre'],
        ], ['nombre.unique' => 'Ya existe un campus con ese nombre.'], ['nombre' => 'nombre']);

        Campus::create($d);
        Auditoria::registrar('Campus agregado', $d['nombre']);

        return back()->with('exito', 'Campus agregado.');
    }

    public function pabellon(Request $request): RedirectResponse
    {
        $d = $request->validateWithBag('pabellon', [
            'campus_id'   => ['required', 'integer', 'exists:campus,id'],
            'nombre'      => ['required', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO,
                Rule::unique('pabellones', 'nombre')->where('campus_id', $request->input('campus_id'))],
            'descripcion' => ['nullable', 'string', 'max:255', 'regex:' . Reglas::TEXTO_CORTO],
            'pisos'       => ['required', 'integer', 'between:1,30'],
        ], ['nombre.unique' => 'Ese campus ya tiene un pabellón con ese nombre.', 'campus_id.required' => 'Elige un campus.']);

        Pabellon::create($d);
        Auditoria::registrar('Pabellón agregado', $d['nombre']);

        return back()->with('exito', 'Pabellón agregado.');
    }

    public function ubicacion(Request $request): RedirectResponse
    {
        $pabellon = Pabellon::find($request->input('pabellon_id'));

        $d = $request->validateWithBag('ubicacion', [
            'pabellon_id' => ['required', 'integer', 'exists:pabellones,id'],
            'piso'        => ['required', 'integer', 'between:1,' . ($pabellon?->pisos ?? 30)],
            'tipo'        => ['required', Rule::in(array_keys(Ubicacion::TIPOS))],
            'nombre'      => ['required', 'string', 'max:120', 'regex:' . Reglas::TEXTO_CORTO,
                Rule::unique('ubicaciones', 'nombre')->where('pabellon_id', $request->input('pabellon_id'))->where('piso', $request->input('piso'))],
        ], [
            'nombre.unique' => 'Ya existe un ambiente con ese nombre en ese piso.',
            'piso.between'  => 'El piso debe estar entre 1 y el total de pisos del pabellón.',
            'pabellon_id.required' => 'Elige un pabellón.',
        ]);

        Ubicacion::create($d);
        Auditoria::registrar('Ubicación agregada', "{$pabellon->nombre} · piso {$d['piso']} · {$d['nombre']}");

        return back()->with('exito', 'Ambiente agregado.');
    }

    public function destroyUbicacion(Ubicacion $ubicacion): RedirectResponse
    {
        if ($ubicacion->incidencias()->exists()) {
            return back()->with('error', 'Ese ambiente tiene incidencias registradas; no se puede eliminar.');
        }
        $ubicacion->delete();
        Auditoria::registrar('Ubicación eliminada', $ubicacion->nombre);

        return back()->with('exito', 'Ambiente eliminado.');
    }

    public function destroyPabellon(Pabellon $pabellon): RedirectResponse
    {
        if ($pabellon->ubicaciones()->exists()) {
            return back()->with('error', 'Primero elimina los ambientes de ese pabellón.');
        }
        $pabellon->delete();
        Auditoria::registrar('Pabellón eliminado', $pabellon->nombre);

        return back()->with('exito', 'Pabellón eliminado.');
    }

    public function destroyCampus(Campus $campus): RedirectResponse
    {
        if ($campus->pabellones()->exists()) {
            return back()->with('error', 'Primero elimina los pabellones de ese campus.');
        }
        $campus->delete();
        Auditoria::registrar('Campus eliminado', $campus->nombre);

        return back()->with('exito', 'Campus eliminado.');
    }
}
