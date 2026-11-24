<?php

namespace App\Services;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Categoria;
use App\Models\Historial;
use App\Models\Incidencia;
use App\Models\Pabellon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Cifras para el panel (según lo que cada rol puede ver) y para los reportes (todo). */
class Estadisticas
{
    private const CERRADAS = ['Resuelta', 'Cerrada'];

    /** @return array{kpis:array,estados:array,categorias:array,pabellones:array,prioridades:array,total:int} */
    /** Con $u: solo lo que esa persona puede ver. Sin $u: todo. */
    public function panel(?User $u = null): array
    {
        $base = fn () => $u ? Incidencia::query()->visiblePara($u) : Incidencia::query();

        $porEstado = $base()->selectRaw('incidencias.estado as clave, count(*) as n')->groupBy('incidencias.estado')->pluck('n', 'clave');
        $porPrioridad = $base()->selectRaw('incidencias.prioridad as clave, count(*) as n')->groupBy('incidencias.prioridad')->pluck('n', 'clave');
        $porCategoria = $base()->selectRaw('incidencias.categoria_id as clave, count(*) as n')->groupBy('incidencias.categoria_id')->pluck('n', 'clave');
        $porPabellon = $base()->join('ubicaciones', 'ubicaciones.id', '=', 'incidencias.ubicacion_id')
            ->selectRaw('ubicaciones.pabellon_id as clave, count(*) as n')->groupBy('ubicaciones.pabellon_id')->pluck('n', 'clave');

        $total = (int) $porEstado->sum();
        $resueltas = (int) $porEstado->only(self::CERRADAS)->sum();
        $criticasActivas = $base()->where('incidencias.prioridad', Prioridad::Critica->value)->whereNotIn('incidencias.estado', self::CERRADAS)->count();

        return [
            'total' => $total,
            'kpis'  => [
                'total'      => $total,
                'sinAsignar' => (int) ($porEstado[EstadoIncidencia::Registrada->value] ?? 0) + (int) ($porEstado[EstadoIncidencia::EnEvaluacion->value] ?? 0),
                'enAtencion' => (int) ($porEstado[EstadoIncidencia::Asignada->value] ?? 0) + (int) ($porEstado[EstadoIncidencia::EnAtencion->value] ?? 0),
                'criticas'   => $criticasActivas,
                'resueltas'  => $resueltas,
                'tasa'       => $total ? (int) round($resueltas / $total * 100) : 0,
            ],
            'estados'     => $this->serie(EstadoIncidencia::cases(), fn ($e) => [$e->value, (int) ($porEstado[$e->value] ?? 0)]),
            'prioridades' => $this->serie(Prioridad::cases(), fn ($p) => [$p->value, (int) ($porPrioridad[$p->value] ?? 0)]),
            'categorias'  => $this->serie(Categoria::orderBy('nombre')->get(), fn ($c) => [$c->nombre, (int) ($porCategoria[$c->id] ?? 0)]),
            'pabellones'  => $this->serie(Pabellon::orderBy('nombre')->get(), fn ($p) => [$p->nombre, (int) ($porPabellon[$p->id] ?? 0)]),
        ];
    }

    /** Reporte ejecutivo: todas las incidencias. */
    public function reporte(): array
    {
        $panel = $this->panel();

        // Horas entre el registro y la primera vez que quedó «Resuelta»
        $resueltas = Historial::where('estado', EstadoIncidencia::Resuelta->value)
            ->selectRaw('incidencia_id, min(created_at) as cuando')->groupBy('incidencia_id')->pluck('cuando', 'incidencia_id');
        $creadas = Incidencia::whereIn('id', $resueltas->keys())->pluck('created_at', 'id');
        $horas = $resueltas->map(fn ($cuando, $id) => isset($creadas[$id]) ? $creadas[$id]->diffInSeconds($cuando, false) / 3600 : null)->filter(fn ($h) => $h !== null && $h >= 0);

        $panel['tiempoMedioHoras'] = $horas->isEmpty() ? null : round($horas->avg(), 1);
        $panel['confianzaMedia'] = (int) round((float) Incidencia::avg('confianza_ia'));
        $panel['corregidas'] = DB::table('analisis_ia')->where('modificada', true)->count();

        return $panel;
    }

    /** @return array<int,array{etiqueta:string,valor:int}> */
    private function serie(iterable $items, callable $f): array
    {
        $salida = [];
        foreach ($items as $item) {
            [$etiqueta, $valor] = $f($item);
            $salida[] = ['etiqueta' => $etiqueta, 'valor' => $valor];
        }

        return $salida;
    }
}
