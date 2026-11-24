<?php

namespace App\Enums;

/** Ciclo de vida de una incidencia (en este orden). */
enum EstadoIncidencia: string
{
    case Registrada   = 'Registrada';
    case EnEvaluacion = 'En evaluación';
    case Asignada     = 'Asignada';
    case EnAtencion   = 'En atención';
    case Resuelta     = 'Resuelta';
    case Cerrada      = 'Cerrada';

    /** Posición en el ciclo: 0 (Registrada) a 5 (Cerrada). */
    public function paso(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** Clase CSS de la insignia. */
    public function clase(): string
    {
        return 'e-' . str_replace(' ', '-', $this->value);
    }

    /** ¿Sigue en trabajo? (no resuelta ni cerrada) */
    public function enCurso(): bool
    {
        return ! in_array($this, [self::Resuelta, self::Cerrada], true);
    }

    /** ¿Aún se puede reclasificar o asignar? */
    public function antesDeAsignar(): bool
    {
        return in_array($this, [self::Registrada, self::EnEvaluacion], true);
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
