<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    public const TIPOS = [
        'aula'        => 'Aula',
        'laboratorio' => 'Laboratorio',
        'area_comun'  => 'Área común',
        'otro'        => 'Otro',
    ];

    protected $table = 'ubicaciones';

    protected $fillable = ['pabellon_id', 'piso', 'tipo', 'nombre'];

    public function pabellon(): BelongsTo
    {
        return $this->belongsTo(Pabellon::class, 'pabellon_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'ubicacion_id');
    }
}
