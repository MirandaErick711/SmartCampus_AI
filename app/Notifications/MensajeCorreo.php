<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo genérico del sistema (confirmación, recuperación, invitación, avisos).
 * Usa la plantilla resources/views/emails/plantilla.blade.php (mismo estilo negro + verde neón).
 */
class MensajeCorreo extends Notification
{
    public function __construct(
        public string $asunto,
        public string $titulo,
        public string $saludo,
        public array $parrafos,
        public ?string $botonTexto = null,
        public ?string $botonUrl = null,
        public string $pie = '',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->asunto)
            ->view(['emails.plantilla', 'emails.plantilla_texto'], [
                'titulo'     => $this->titulo,
                'saludo'     => $this->saludo,
                'parrafos'   => $this->parrafos,
                'botonTexto' => $this->botonTexto,
                'botonUrl'   => $this->botonUrl,
                'pie'        => $this->pie,
            ]);
    }
}
