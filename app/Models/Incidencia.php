<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Incidencia extends Model
{
    protected $table = 'incidencias';

    // Solo el servidor (IncidenciaService) escribe aquí, con datos ya validados.
    protected $fillable = [
        'codigo', 'reportante_id', 'tecnico_id', 'ubicacion_id', 'area_id', 'categoria_id', 'subcategoria_id',
        'estado', 'prioridad', 'titulo', 'descripcion', 'confianza_ia', 'informe_tecnico',
    ];

    protected function casts(): array
    {
        return [
            'estado'    => EstadoIncidencia::class,
            'prioridad' => Prioridad::class,
        ];
    }

    public function reportante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportante_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class, 'incidencia_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(Historial::class, 'incidencia_id')->orderBy('id');
    }

    public function analisis(): HasOne
    {
        return $this->hasOne(AnalisisIa::class, 'incidencia_id');
    }

    /** Solo las incidencias que esa persona tiene derecho a ver. */
    public function scopeVisiblePara(Builder $q, User $u): Builder
    {
        return match ($u->grupo()) {
            'reportante' => $q->where('incidencias.reportante_id', $u->id),
            'tecnico'    => $q->where('incidencias.tecnico_id', $u->id),
            default      => $q, // operador y administrador: todas
        };
    }

    public function evidenciaDe(string $tipo): ?Evidencia
    {
        return $this->evidencias->firstWhere('tipo', $tipo);
    }
}
