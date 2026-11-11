<?php

namespace App\Services;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Exceptions\ReglaIncidencia;
use App\Models\AnalisisIa;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Evidencia;
use App\Models\Historial;
use App\Models\Incidencia;
use App\Models\Subcategoria;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Todas las reglas del ciclo de vida de una incidencia viven aquí:
 * Registrada → En evaluación → Asignada → En atención → Resuelta → Cerrada.
 * Los controladores solo validan datos y llaman a estos métodos.
 */
class IncidenciaService
{
    public const DISCO = 'local'; // privado: storage/app/private (nunca es público)

    public function __construct(private readonly MotorIA $motor, private readonly NotificacionService $avisos)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  Reportar                                                           */
    /* ------------------------------------------------------------------ */

    /** @param array{titulo:string,descripcion:string,ubicacion_id:int,area_id:int,categoria_id?:?int,subcategoria_id?:?int,prioridad?:?string} $d */
    public function crear(User $autor, array $d, ?UploadedFile $foto = null): Incidencia
    {
        $ubicacion = Ubicacion::with('pabellon')->findOrFail($d['ubicacion_id']);
        $area = Area::findOrFail($d['area_id']);

        $ia = $this->motor->analizar($d['titulo'], $d['descripcion'], $foto !== null, $ubicacion->pabellon->nombre, $ubicacion->nombre, $area->nombre);
        [$iaCat, $iaSub] = $this->idsDeIa($ia);

        $catId = (int) ($d['categoria_id'] ?? 0) ?: $iaCat;
        $subId = (int) ($d['subcategoria_id'] ?? 0) ?: null;
        if (! $subId && $catId === $iaCat) {
            $subId = $iaSub;
        }
        $prioridad = ! empty($d['prioridad']) ? Prioridad::from($d['prioridad']) : $ia['prioridad'];
        $modificada = $catId !== $iaCat || $subId !== $iaSub || $prioridad !== $ia['prioridad'];

        $rutaFoto = null;
        try {
            return DB::transaction(function () use ($autor, $d, $ia, $iaCat, $iaSub, $catId, $subId, $prioridad, $modificada, $foto, &$rutaFoto) {
                $inc = Incidencia::create([
                    'reportante_id'   => $autor->id,
                    'ubicacion_id'    => $d['ubicacion_id'],
                    'area_id'         => $d['area_id'],
                    'categoria_id'    => $catId,
                    'subcategoria_id' => $subId,
                    'estado'          => EstadoIncidencia::Registrada,
                    'prioridad'       => $prioridad,
                    'titulo'          => $d['titulo'],
                    'descripcion'     => $d['descripcion'],
                    'confianza_ia'    => $ia['confianza'],
                ]);
                $inc->update(['codigo' => sprintf('INC-%s-%04d', now()->format('Y'), $inc->id)]);

                $this->anotar($inc, $autor, 'Incidencia registrada por el usuario');

                AnalisisIa::create([
                    'incidencia_id'   => $inc->id,
                    'categoria_id'    => $iaCat,
                    'subcategoria_id' => $iaSub,
                    'prioridad'       => $ia['prioridad'],
                    'confianza'       => $ia['confianza'],
                    'palabras_clave'  => implode(', ', $ia['hits']),
                    'objetos'         => implode(', ', $ia['objetos']),
                    'explicacion'     => implode("\n", $ia['explicacion']),
                    'modificada'      => $modificada,
                ]);

                if ($foto) {
                    $rutaFoto = $this->guardarFoto($inc, $foto, 'reporte', $autor)->ruta;
                }

                Auditoria::registrar('Incidencia registrada', "{$inc->codigo} · {$inc->titulo}", $autor);

                $critica = $prioridad === Prioridad::Critica;
                $this->avisos->aGrupo('operador', ($critica ? 'Nueva incidencia CRÍTICA: ' : 'Nueva incidencia registrada: ') . $inc->titulo, $critica ? 'critica' : 'info', $inc, $autor->id);
                $this->avisos->aGrupo('administrador', "Nueva incidencia {$inc->codigo}: {$inc->titulo}", 'info', $inc, $autor->id);

                return $inc;
            });
        } catch (\Throwable $e) {
            if ($rutaFoto) {
                Storage::disk(self::DISCO)->delete($rutaFoto); // no dejar fotos huérfanas
            }
            throw $e;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Operador / Administrador                                           */
    /* ------------------------------------------------------------------ */

    public function evaluar(Incidencia $inc, User $u): void
    {
        $this->transicion($inc, function (Incidencia $i) use ($u) {
            $this->exigir($i->estado === EstadoIncidencia::Registrada, 'La incidencia ya fue evaluada.');
            $this->cambiarEstado($i, EstadoIncidencia::EnEvaluacion, $u, 'Operador inició la evaluación');
            Auditoria::registrar('Estado actualizado', "{$i->codigo} · Registrada → En evaluación", $u);
            $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} está «En evaluación»", 'estado', $i, $u->id);
        });
    }

    public function validarIa(Incidencia $inc, User $u): void
    {
        $this->transicion($inc, function (Incidencia $i) use ($u) {
            $this->exigir($i->estado->antesDeAsignar(), 'Solo se valida la clasificación antes de asignar el técnico.');
            $i->analisis?->update(['validada' => true, 'modificada' => false]);
            $eraRegistrada = $i->estado === EstadoIncidencia::Registrada;
            $this->avanzarOAnotar($i, $u, 'Clasificación IA validada por el operador');
            if ($eraRegistrada) {
                $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} está «En evaluación»", 'estado', $i, $u->id);
            }
            Auditoria::registrar('Incidencia evaluada', "{$i->codigo} · Clasificación IA validada ({$i->categoria->nombre} / {$i->prioridad->value})", $u);
        });
    }

    public function reclasificar(Incidencia $inc, User $u, int $categoriaId, ?int $subcategoriaId, Prioridad $prioridad): void
    {
        $this->transicion($inc, function (Incidencia $i) use ($u, $categoriaId, $subcategoriaId, $prioridad) {
            $this->exigir($i->estado->antesDeAsignar(), 'Solo se reclasifica antes de asignar el técnico.');
            $antes = "{$i->categoria->nombre}/" . ($i->subcategoria?->nombre ?? '—') . "/{$i->prioridad->value}";

            $i->update(['categoria_id' => $categoriaId, 'subcategoria_id' => $subcategoriaId, 'prioridad' => $prioridad]);
            $i->analisis?->update(['modificada' => true, 'validada' => false]);
            $i->load('categoria', 'subcategoria');

            $this->avanzarOAnotar($i, $u, 'Clasificación ajustada manualmente');
            $despues = "{$i->categoria->nombre}/" . ($i->subcategoria?->nombre ?? '—') . "/{$prioridad->value}";
            Auditoria::registrar('Clasificación modificada', "{$i->codigo} · {$antes} → {$despues}", $u);
        });
    }

    public function asignar(Incidencia $inc, User $u, User $tecnico): void
    {
        $this->exigir($tecnico->grupo() === 'tecnico' && $tecnico->estado === User::ACTIVA, 'Selecciona un técnico disponible.');

        $this->transicion($inc, function (Incidencia $i) use ($u, $tecnico) {
            $this->exigir($i->estado->antesDeAsignar(), 'Solo se asigna desde «Registrada» o «En evaluación».');
            if ($i->estado === EstadoIncidencia::Registrada) {
                $this->cambiarEstado($i, EstadoIncidencia::EnEvaluacion, $u, 'Evaluación automática previa a la asignación');
            }
            $i->update(['tecnico_id' => $tecnico->id]);
            $this->cambiarEstado($i, EstadoIncidencia::Asignada, $u, 'Asignada a ' . $tecnico->nombres);
            Auditoria::registrar('Técnico asignado', "{$i->codigo} · Asignada a {$tecnico->nombres}", $u);
            $this->avisos->aUsuario($tecnico->id, "Se te asignó {$i->codigo} · {$i->titulo} ({$i->prioridad->value})", $i->prioridad === Prioridad::Critica ? 'critica' : 'asignacion', $i);
            $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} fue asignada a {$tecnico->nombres}", 'estado', $i, $u->id);
        });
    }

    public function cerrar(Incidencia $inc, User $u): void
    {
        $this->transicion($inc, function (Incidencia $i) use ($u) {
            $this->exigir($i->estado === EstadoIncidencia::Resuelta, 'Solo se puede cerrar una incidencia «Resuelta».');
            $this->cambiarEstado($i, EstadoIncidencia::Cerrada, $u, 'Cierre definitivo confirmado por el operador');
            Auditoria::registrar('Estado actualizado', "{$i->codigo} · Resuelta → Cerrada", $u);
            $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} fue cerrada. ¡Gracias por reportar!", 'estado', $i, $u->id);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Técnico                                                            */
    /* ------------------------------------------------------------------ */

    public function iniciar(Incidencia $inc, User $tecnico): void
    {
        $this->transicion($inc, function (Incidencia $i) use ($tecnico) {
            $this->exigirAsignado($i, $tecnico);
            $this->exigir($i->estado === EstadoIncidencia::Asignada, 'Acción no permitida para esta incidencia.');
            $this->cambiarEstado($i, EstadoIncidencia::EnAtencion, $tecnico, 'Técnico inició la reparación');
            Auditoria::registrar('Estado actualizado', "{$i->codigo} · Asignada → En atención", $tecnico);
            $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} pasó a «En atención»", 'estado', $i, $tecnico->id);
            $this->avisos->aGrupo('operador', "{$i->codigo} está en atención ({$tecnico->nombres})", 'info', $i);
        });
    }

    public function resolver(Incidencia $inc, User $tecnico, string $informe, ?UploadedFile $foto = null): void
    {
        $rutaFoto = null;
        try {
            $this->transicion($inc, function (Incidencia $i) use ($tecnico, $informe, $foto, &$rutaFoto) {
                $this->exigirAsignado($i, $tecnico);
                $this->exigir($i->estado === EstadoIncidencia::EnAtencion, 'Acción no permitida para esta incidencia.');

                $i->update(['informe_tecnico' => $informe]);
                if ($foto) {
                    $rutaFoto = $this->guardarFoto($i, $foto, 'solucion', $tecnico)->ruta;
                }
                $this->cambiarEstado($i, EstadoIncidencia::Resuelta, $tecnico, 'Informe técnico registrado');
                Auditoria::registrar('Estado actualizado', "{$i->codigo} · En atención → Resuelta", $tecnico);
                $this->avisos->aGrupo('operador', "{$i->codigo} fue marcada como «Resuelta» por {$tecnico->nombres}. Pendiente de cierre.", 'estado', $i);
                $this->avisos->aUsuario($i->reportante_id, "Tu incidencia {$i->codigo} fue marcada como «Resuelta»", 'estado', $i, $tecnico->id);
            });
        } catch (\Throwable $e) {
            if ($rutaFoto) {
                Storage::disk(self::DISCO)->delete($rutaFoto);
            }
            throw $e;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Utilidades                                                         */
    /* ------------------------------------------------------------------ */

    /** Técnicos activos con su carga de trabajo (para el selector de asignación). */
    public function tecnicosDisponibles()
    {
        return User::query()
            ->where('estado', User::ACTIVA)
            ->whereHas('rol', fn ($q) => $q->where('grupo', 'tecnico'))
            ->withCount(['incidenciasAsignadas as activos' => fn ($q) => $q->whereNotIn('estado', [EstadoIncidencia::Resuelta->value, EstadoIncidencia::Cerrada->value])])
            ->orderBy('nombres')
            ->get();
    }

    /** Ejecuta la acción dentro de una transacción, con la incidencia bloqueada (evita que dos personas la cambien a la vez). */
    private function transicion(Incidencia $inc, \Closure $accion): void
    {
        DB::transaction(function () use ($inc, $accion) {
            $fresca = Incidencia::with(['analisis', 'categoria', 'subcategoria'])->whereKey($inc->id)->lockForUpdate()->firstOrFail();
            $accion($fresca);
        });
    }

    private function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new ReglaIncidencia($mensaje);
        }
    }

    private function exigirAsignado(Incidencia $i, User $tecnico): void
    {
        abort_unless($i->tecnico_id === $tecnico->id, 403, 'Esta incidencia no está asignada a ti.');
    }

    private function cambiarEstado(Incidencia $i, EstadoIncidencia $nuevo, User $u, string $nota): void
    {
        $i->update(['estado' => $nuevo]);
        $this->anotar($i, $u, $nota);
    }

    /** Agrega una línea al historial con el estado actual. */
    private function anotar(Incidencia $i, User $u, string $nota): void
    {
        Historial::create(['incidencia_id' => $i->id, 'estado' => $i->estado, 'usuario_id' => $u->id, 'nota' => mb_substr($nota, 0, 255)]);
        $i->touch();
    }

    /** Si aún está «Registrada» pasa a «En evaluación»; si no, solo deja una nota. */
    private function avanzarOAnotar(Incidencia $i, User $u, string $nota): void
    {
        $i->estado === EstadoIncidencia::Registrada
            ? $this->cambiarEstado($i, EstadoIncidencia::EnEvaluacion, $u, $nota)
            : $this->anotar($i, $u, $nota);
    }

    private function guardarFoto(Incidencia $i, UploadedFile $foto, string $tipo, User $autor): Evidencia
    {
        $ruta = $foto->store('evidencias/' . now()->format('Y/m'), self::DISCO); // nombre aleatorio; nunca el del usuario

        return Evidencia::create([
            'incidencia_id' => $i->id,
            'tipo'          => $tipo,
            'ruta'          => $ruta,
            'mime'          => (string) $foto->getMimeType(),
            'tamano'        => (int) $foto->getSize(),
            'subido_por'    => $autor->id,
        ]);
    }

    /** @return array{0:int,1:?int} ids de categoría y subcategoría que sugirió la IA */
    private function idsDeIa(array $ia): array
    {
        $cat = Categoria::where('nombre', $ia['categoria'])->firstOrFail();
        $sub = Subcategoria::where('categoria_id', $cat->id)->where('nombre', $ia['subcategoria'])->first();

        return [$cat->id, $sub?->id];
    }
}
