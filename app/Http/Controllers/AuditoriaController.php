<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/** Historial de todo lo que ocurre en la plataforma (solo lectura). */
class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $d = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $registros = Auditoria::query()
            ->with('usuario.rol')
            ->when($d['q'] ?? null, function ($q, $v) {
                $like = '%' . addcslashes($v, '\\%_') . '%';
                $q->where(fn ($x) => $x->where('actor', 'like', $like)->orWhere('accion', 'like', $like)->orWhere('detalle', 'like', $like));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('auditoria.index', ['registros' => $registros, 'q' => $d['q'] ?? '']);
    }
}
