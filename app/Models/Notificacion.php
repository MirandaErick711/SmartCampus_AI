<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['user_id', 'texto', 'tipo', 'incidencia_id', 'leida'];

    protected function casts(): array
    {
        return ['leida' => 'boolean'];
    }
}
