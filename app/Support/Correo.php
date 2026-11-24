<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\MensajeCorreo;
use Illuminate\Support\Facades\Notification;
use Throwable;

class Correo
{
    /**
     * Envía un correo sin romper la página si el servidor de correo falla.
     * Devuelve true si se envió y false si hubo un error (queda registrado en storage/logs/laravel.log).
     */
    public static function enviar(User|string $destino, MensajeCorreo $mensaje): bool
    {
        try {
            if ($destino instanceof User) {
                $destino->notify($mensaje);
            } else {
                Notification::route('mail', $destino)->notify($mensaje);
            }
            return true;
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }
}
