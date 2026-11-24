<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    // Solo el servidor escribe aquí (nunca con datos directos del formulario).
    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Deja constancia de quién hizo qué y cuándo. */
    public static function registrar(string $accion, ?string $detalle = null, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        static::create([
            'user_id'    => $actor?->id,
            'actor'      => $actor?->nombres,
            'accion'     => $accion,
            'detalle'    => $detalle,
            'ip'         => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
