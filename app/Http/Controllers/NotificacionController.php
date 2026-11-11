<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La campana: cada persona solo ve y marca sus propias notificaciones. */
class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $uid = $request->user()->id;

        return response()->json([
            'no_leidas' => Notificacion::where('user_id', $uid)->where('leida', false)->count(),
            'items'     => Notificacion::where('user_id', $uid)->latest('id')->limit(25)->get()->map(fn ($n) => [
                'id'    => $n->id,
                'texto' => $n->texto,
                'tipo'  => $n->tipo,
                'leida' => $n->leida,
                'fecha' => $n->created_at->format('d/m H:i'),
                'url'   => $n->incidencia_id ? route('incidencias.show', $n->incidencia_id) : null,
            ]),
        ]);
    }

    public function leer(Request $request, Notificacion $notificacion): JsonResponse
    {
        abort_unless($notificacion->user_id === $request->user()->id, 404);

        $notificacion->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        Notificacion::where('user_id', $request->user()->id)->where('leida', false)->update(['leida' => true]);

        return response()->json(['ok' => true]);
    }
}
