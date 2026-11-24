<?php

namespace App\Enums;

enum Prioridad: string
{
    case Baja    = 'Baja';
    case Media   = 'Media';
    case Alta    = 'Alta';
    case Critica = 'Crítica';

    public function nivel(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function claseInsignia(): string
    {
        return 'b-' . $this->value;
    }

    public function claseBorde(): string
    {
        return 'pc-' . $this->value;
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
