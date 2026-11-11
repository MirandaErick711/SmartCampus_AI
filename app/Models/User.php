<?php

namespace App\Models;

use App\Notifications\MensajeCorreo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

class User extends Authenticatable
{
    use Notifiable;

    public const PENDIENTE = 'pendiente';   // espera la aprobación del administrador
    public const ACTIVA    = 'activa';
    public const INACTIVA  = 'inactiva';    // desactivada por el administrador
    public const INVITADA  = 'invitada';    // invitada: aún no creó su contraseña
    public const CERRADA   = 'cerrada';     // cerrada para siempre (relevo de administrador)

    protected $table = 'users';

    // Solo estos campos pueden asignarse en masa. "estado", "rol_id" y similares los decide el servidor (forceFill).
    protected $fillable = ['nombres', 'email', 'password', 'departamento'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'ultimo_acceso'      => 'datetime',
            'password'           => 'hashed',
            'debe_cambiar_clave' => 'boolean',
        ];
    }

        public function incidenciasAsignadas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Incidencia::class, 'tecnico_id');
    }

public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function grupo(): string
    {
        return (string) $this->rol?->grupo;
    }

    public function esAdministrador(): bool
    {
        return $this->grupo() === 'administrador';
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/u', trim($this->nombres)) ?: [];
        $ini = '';
        foreach (array_slice($partes, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini !== '' ? $ini : '?';
    }

    /** Texto y color del estado para las etiquetas del panel. */
    public function etiquetaEstado(): array
    {
        return match ($this->estado) {
            self::ACTIVA    => ['Activa', 'e-Resuelta'],
            self::PENDIENTE => ['Pendiente de aprobación', 'e-En-evaluación'],
            self::INVITADA  => ['Invitación enviada', 'e-Asignada'],
            self::INACTIVA  => ['Desactivada', 'e-Cerrada'],
            default         => ['Cerrada', 'e-Cerrada'],
        };
    }

    /** Enlace firmado y con vencimiento para confirmar el correo. */
    public function urlConfirmacionCorreo(): string
    {
        return URL::temporarySignedRoute(
            'correo.verificar',
            now()->addMinutes(config('smartcampus.verificacion_min')),
            ['id' => $this->id, 'hash' => sha1($this->email)]
        );
    }

    /** Correo de "¿Olvidaste tu contraseña?" (lo invoca el sistema de recuperación de Laravel). */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $min = (int) config('auth.passwords.users.expire');

        $this->notify(new MensajeCorreo(
            asunto: 'Restablece tu contraseña · SmartCampus AI',
            titulo: 'Restablece tu contraseña',
            saludo: 'Hola, ' . $this->nombres . ':',
            parrafos: [
                'Recibimos una solicitud para restablecer la contraseña de tu cuenta de SmartCampus AI.',
                "Pulsa el botón para elegir una nueva. El enlace es de un solo uso y vence en $min minutos.",
            ],
            botonTexto: 'Restablecer contraseña',
            botonUrl: $url,
            pie: 'Si tú no lo solicitaste, ignora este mensaje: tu contraseña no cambiará.',
        ));
    }
}
