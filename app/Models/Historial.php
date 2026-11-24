<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Historial extends Model
{
    protected $table = 'historial';

    protected $fillable = ['incidencia_id', 'estado', 'usuario_id', 'nota'];

    protected function casts(): array
    {
        return ['estado' => EstadoIncidencia::class];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
