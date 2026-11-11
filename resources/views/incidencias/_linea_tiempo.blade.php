{{-- Línea de tiempo de 6 fases. Parámetros: $inc (con historial), $compacta (false) --}}
@php $actual = $inc->estado->paso(); @endphp
<div class="timeline {{ ! empty($compacta) ? 'compact' : '' }}">
    @foreach (\App\Enums\EstadoIncidencia::cases() as $e)
        @php
            $clase = $e->paso() < $actual ? 'done' : ($e->paso() === $actual ? 'current' : '');
            $h = $inc->historial->first(fn ($x) => $x->estado === $e);
        @endphp
        <div class="tl-step {{ $clase }}">
            <div class="tl-dot">@if ($e->paso() < $actual)<i data-lucide="check"></i>@else{{ $e->paso() + 1 }}@endif</div>
            <div class="tl-txt">
                <div class="tl-label">{{ $e->value }}</div>
                <div class="tl-date">{{ $h && $e->paso() <= $actual ? $h->created_at->format('d/m · H:i') : 'Pendiente' }}</div>
            </div>
        </div>
    @endforeach
</div>
