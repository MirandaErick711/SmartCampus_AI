<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    protected $table = 'campus';

    protected $fillable = ['nombre'];

    public function pabellones(): HasMany
    {
        return $this->hasMany(Pabellon::class, 'campus_id')->orderBy('nombre');
    }
}
