<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class, 'categoria_id')->orderBy('nombre');
    }
}
