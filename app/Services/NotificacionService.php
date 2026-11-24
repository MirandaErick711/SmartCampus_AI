<?php

namespace App\Services;

use App\Models\Incidencia;
use App\Models\Notificacion;
use App\Models\User;

/** Avisos internos (la campana). Solo el servidor los crea. */
class NotificacionService
{
    public function aUsuario(?int $userId, string $texto, string $tipo = 'info', ?Incidencia $inc = null, ?int $excluir = null): void
    {
        if (! $userId || $userId === $excluir) {
            return;
        }

        Notificacion::create([
            'user_id'       => $userId,
            'texto'         => mb_substr($texto, 0, 255),
            'tipo'          => $tipo,
            'incidencia_id' => $inc?->id,
        ]);
    }

    /** Avisa a todas las cuentas activas de un grupo de roles (operador, administrador…). */
    public function aGrupo(string $grupo, string $texto, string $tipo = 'info', ?Incidencia $inc = null, ?int $excluir = null): void
    {
        $ids = User::query()
            ->where('estado', User::ACTIVA)
            ->when($excluir, fn ($q) => $q->where('id', '!=', $excluir))
            ->whereHas('rol', fn ($q) => $q->where('grupo', $grupo))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $ahora = now();
        Notificacion::insert($ids->map(fn ($id) => [
            'user_id'       => $id,
            'texto'         => mb_substr($texto, 0, 255),
            'tipo'          => $tipo,
            'incidencia_id' => $inc?->id,
            'leida'         => false,
            'created_at'    => $ahora,
            'updated_at'    => $ahora,
        ])->all());
    }
}
