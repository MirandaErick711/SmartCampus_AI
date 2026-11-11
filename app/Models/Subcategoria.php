<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subcategoria extends Model
{
    protected $table = 'subcategorias';

    public $timestamps = false;

    protected $fillable = ['categoria_id', 'nombre'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
