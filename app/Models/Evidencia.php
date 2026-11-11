<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidencia extends Model
{
    protected $table = 'evidencias';

    protected $fillable = ['incidencia_id', 'tipo', 'ruta', 'mime', 'tamano', 'subido_por'];

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_id');
    }
}
