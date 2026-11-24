<?php

namespace App\Models;

use App\Enums\Prioridad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisIa extends Model
{
    protected $table = 'analisis_ia';

    protected $fillable = [
        'incidencia_id', 'categoria_id', 'subcategoria_id', 'prioridad', 'confianza',
        'palabras_clave', 'objetos', 'explicacion', 'validada', 'modificada',
    ];

    protected function casts(): array
    {
        return ['prioridad' => Prioridad::class, 'validada' => 'boolean', 'modificada' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    /** Lista de palabras clave / objetos como arreglo. */
    public function lista(string $campo): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->{$campo}))));
    }
}
