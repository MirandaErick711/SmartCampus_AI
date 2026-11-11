<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pabellon extends Model
{
    protected $table = 'pabellones';

    protected $fillable = ['campus_id', 'nombre', 'descripcion', 'pisos'];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class, 'pabellon_id')->orderBy('piso')->orderBy('nombre');
    }
}
