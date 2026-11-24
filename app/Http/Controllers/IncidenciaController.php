<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Http\Requests\IncidenciaRequest;
use App\Models\Area;
use App\Models\Campus;
use App\Models\Categoria;
use App\Models\Incidencia;
use App\Models\Pabellon;
use App\Models\Ubicacion;
use App\Services\IncidenciaService;
use App\Services\MotorIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidenciaController extends Controller
{
    public function __construct(private readonly IncidenciaService $servicio)
    {
    }

    /** Bandeja: cada persona ve solo lo suyo (reportante: lo que reportó; técnico: lo asignado; operador/admin: todo). */
    public function index(Request $request)
    {
        $u = $request->user();

        $filtros = $request->validate([
            'q'         => ['nullable', 'string', 'max:100'],
            'estado'    => ['nullable', 'in:' . implode(',', EstadoIncidencia::valores())],
            'prioridad' => ['nullable', 'in:' . implode(',', Prioridad::valores())],
            'categoria' => ['nullable', 'integer'],
            'pabellon'  => ['nullable', 'integer'],
        ]);

        $q = Incidencia::query()->visiblePara($u)
            ->with(['categoria', 'ubicacion.pabellon', 'tecnico', 'evidencias', 'historial'])
            ->when($filtros['estado'] ?? null, fn ($c, $v) => $c->where('estado', $v))
            ->when($filtros['prioridad'] ?? null, fn ($c, $v) => $c->where('prioridad', $v))
            ->when($filtros['categoria'] ?? null, fn ($c, $v) => $c->where('categoria_id', $v))
            ->when($filtros['pabellon'] ?? null, fn ($c, $v) => $c->whereHas('ubicacion', fn ($x) => $x->where('pabellon_id', $v)))
            ->when($filtros['q'] ?? null, function ($c, $v) {
                $like = '%' . addcslashes($v, '\\%_') . '%';
                $c->where(fn ($x) => $x->where('codigo', 'like', $like)->orWhere('titulo', 'like', $like)->orWhere('descripcion', 'like', $like));
            });

        if ($u->grupo() === 'tecnico') {
            // Pendientes primero, de más a menos urgente
            $q->orderByRaw("CASE WHEN estado IN ('Resuelta','Cerrada') THEN 1 ELSE 0 END")
              ->orderByRaw("CASE prioridad WHEN 'Crítica' THEN 4 WHEN 'Alta' THEN 3 WHEN 'Media' THEN 2 ELSE 1 END DESC")
              ->orderBy('created_at');
        } else {
            $q->latest('updated_at');
        }

        return view('incidencias.index', [
            'incidencias' => $q->paginate(12)->withQueryString(),
            'filtros'     => $filtros,
            'categorias'  => Categoria::orderBy('nombre')->get(),
            'pabellones'  => Pabellon::orderBy('nombre')->get(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(in_array($request->user()->grupo(), ['reportante', 'operador', 'administrador'], true), 403, 'Tu rol no reporta incidencias.');

        $ubicaciones = Campus::with('pabellones.ubicaciones')->orderBy('nombre')->get()->map(fn ($c) => [
            'nombre'     => $c->nombre,
            'pabellones' => $c->pabellones->map(fn ($p) => [
                'nombre'  => $p->nombre,
                'pisos'   => $p->ubicaciones->groupBy('piso')->map(fn ($g, $piso) => [
                    'piso'   => (int) $piso,
                    'lugares' => $g->map(fn ($l) => ['id' => $l->id, 'nombre' => $l->nombre])->values(),
                ])->values(),
            ])->values(),
        ])->values();

        $categorias = Categoria::with('subcategorias')->orderBy('nombre')->get();

        return view('incidencias.create', [
            'ubicaciones'      => $ubicaciones,
            'categorias'       => $categorias,
            'categoriasJson'   => $categorias->map(fn ($c) => ['id' => $c->id, 'subs' => $c->subcategorias->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()])->values(),
            'areas'            => Area::orderBy('nombre')->get(),
            'prioridades'      => Prioridad::cases(),
        ]);
    }

    public function store(IncidenciaRequest $request)
    {
        $d = $request->safe()->except('foto');
        $inc = $this->servicio->crear($request->user(), $d, $request->file('foto'));

        return redirect()->route('incidencias.show', $inc)->with('exito', "Incidencia {$inc->codigo} registrada correctamente.");
    }

    public function show(Request $request, Incidencia $incidencia)
    {
        Gate::authorize('ver', $incidencia);

        $incidencia->load(['reportante', 'tecnico', 'ubicacion.pabellon.campus', 'area', 'categoria', 'subcategoria', 'evidencias', 'historial.usuario', 'analisis.categoria', 'analisis.subcategoria']);
        $u = $request->user();
        $gestiona = in_array($u->grupo(), ['operador', 'administrador'], true);

        return view('incidencias.show', [
            'inc'         => $incidencia,
            'gestiona'    => $gestiona,
            'esMiTarea'   => $u->grupo() === 'tecnico' && $incidencia->tecnico_id === $u->id,
            'tecnicos'    => $gestiona && $incidencia->estado->antesDeAsignar() ? $this->servicio->tecnicosDisponibles() : collect(),
            'categorias'  => $gestiona ? Categoria::with('subcategorias')->orderBy('nombre')->get() : collect(),
            'prioridades' => Prioridad::cases(),
        ]);
    }

    /** Vista previa de la clasificación por IA (no guarda nada). Responde JSON para el formulario. */
    public function analizar(Request $request, MotorIA $motor): JsonResponse
    {
        abort_unless(in_array($request->user()->grupo(), ['reportante', 'operador', 'administrador'], true), 403);

        $d = $request->validate([
            'titulo'       => ['nullable', 'string', 'max:180'],
            'descripcion'  => ['nullable', 'string', 'max:4000'],
            'ubicacion_id' => ['nullable', 'integer'],
            'area_id'      => ['nullable', 'integer'],
            'tiene_foto'   => ['nullable', 'boolean'],
        ]);
        if (blank($d['titulo'] ?? null) && blank($d['descripcion'] ?? null)) {
            return response()->json(['message' => 'Escribe un título o una descripción para analizar.'], 422);
        }

        $ubicacion = ! empty($d['ubicacion_id']) ? Ubicacion::with('pabellon')->find($d['ubicacion_id']) : null;
        $area = ! empty($d['area_id']) ? Area::find($d['area_id']) : null;

        $r = $motor->analizar(strip_tags($d['titulo'] ?? ''), strip_tags($d['descripcion'] ?? ''), (bool) ($d['tiene_foto'] ?? false),
            $ubicacion?->pabellon->nombre ?? '', $ubicacion?->nombre ?? '', $area?->nombre ?? '');

        $cat = Categoria::where('nombre', $r['categoria'])->first();
        $sub = $cat?->subcategorias()->where('nombre', $r['subcategoria'])->first();

        return response()->json([
            'categoria_id'    => $cat?->id,
            'categoria'       => $r['categoria'],
            'subcategoria_id' => $sub?->id,
            'subcategoria'    => $r['subcategoria'],
            'prioridad'       => $r['prioridad']->value,
            'confianza'       => $r['confianza'],
            'hits'            => $r['hits'],
            'objetos'         => $r['objetos'],
            'explicacion'     => $r['explicacion'],
        ]);
    }
}
