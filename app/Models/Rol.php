<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['slug', 'nombre', 'grupo', 'requiere_aprobacion', 'registro_publico'];

    protected function casts(): array
    {
        return [
            'requiere_aprobacion' => 'boolean',
            'registro_publico'    => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}
