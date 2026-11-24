<?php

namespace App\Http\Controllers;

use App\Models\Incidencia;
use App\Services\Estadisticas;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reporte ejecutivo y exportación (operador y administrador). */
class ReporteController extends Controller
{
    public function index(Estadisticas $estadisticas)
    {
        return view('reportes.index', [
            'datos'       => $estadisticas->reporte(),
            'incidencias' => Incidencia::with(['categoria', 'ubicacion.pabellon', 'tecnico'])->latest('id')->paginate(20),
        ]);
    }

    public function csv(): StreamedResponse
    {
        $nombre = 'incidencias-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // para que Excel lea bien las tildes
            fputcsv($f, ['Código', 'Título', 'Descripción', 'Campus', 'Pabellón', 'Piso', 'Aula', 'Área', 'Categoría', 'Subcategoría',
                'Prioridad', 'Estado', 'Confianza IA', 'Reportante', 'Técnico', 'Informe técnico', 'Registrada', 'Actualizada']);

            Incidencia::with(['ubicacion.pabellon.campus', 'area', 'categoria', 'subcategoria', 'reportante', 'tecnico'])
                ->orderBy('id')->chunk(200, function ($lote) use ($f) {
                    foreach ($lote as $i) {
                        fputcsv($f, array_map([$this, 'celda'], [
                            $i->codigo, $i->titulo, $i->descripcion, $i->ubicacion->pabellon->campus->nombre, $i->ubicacion->pabellon->nombre,
                            $i->ubicacion->piso, $i->ubicacion->nombre, $i->area->nombre, $i->categoria->nombre, $i->subcategoria?->nombre,
                            $i->prioridad->value, $i->estado->value, $i->confianza_ia, $i->reportante->nombres, $i->tecnico?->nombres,
                            $i->informe_tecnico, $i->created_at->format('Y-m-d H:i'), $i->updated_at->format('Y-m-d H:i'),
                        ]));
                    }
                });
            fclose($f);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita que Excel ejecute como fórmula un texto que empiece con = + - @ (inyección de fórmulas). */
    private function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        return $texto !== '' && str_contains("=+-@\t\r", $texto[0]) ? "'" . $texto : $texto;
    }
}
