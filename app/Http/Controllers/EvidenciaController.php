<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Services\IncidenciaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Entrega las fotos guardadas en el disco privado, solo a quien tiene permiso sobre la incidencia. */
class EvidenciaController extends Controller
{
    public function __invoke(Evidencia $evidencia)
    {
        Gate::authorize('ver', $evidencia->incidencia);

        $disco = Storage::disk(IncidenciaService::DISCO);
        abort_unless($disco->exists($evidencia->ruta), 404, 'Imagen no disponible.');

        return $disco->response($evidencia->ruta, 'evidencia-' . $evidencia->id, [
            'Content-Type'           => $evidencia->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=86400',
        ]);
    }
}
