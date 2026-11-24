@extends('layouts.app')

@section('titulo', 'Reportes')

@php
    $barra = function (string $etiqueta, int $n, int $total, string $color) {
        $ancho = $total ? round($n / $total * 100) : 0;
        return compact('etiqueta', 'n', 'ancho', 'color');
    };
    $total = $datos['total'];
    $colPrioridad = ['Baja' => '#8c94a3', 'Media' => '#00FF66', 'Alta' => '#FFB800', 'Crítica' => '#FF2A5F'];
    $colEstado = ['#cfd5e0', '#FFB800', '#00E5FF', '#00E5FF', '#00FF66', '#5b6372'];
    $colCategoria = ['#FFB800', '#00E5FF', '#00FF66', '#B026FF', '#FF2A5F', '#8c94a3', '#ff7a00', '#4d7cff'];
    $grupos = [
        ['tags', 'Por categoría', $datos['categorias'], fn ($i, $s) => $colCategoria[$i % count($colCategoria)]],
        ['flame', 'Por prioridad', array_reverse($datos['prioridades']), fn ($i, $s) => $colPrioridad[$s['etiqueta']] ?? '#8c94a3'],
        ['activity', 'Por estado', $datos['estados'], fn ($i, $s) => $colEstado[$i % count($colEstado)]],
    ];
@endphp

@section('vista')
<div class="page-h">
    <div>
        <h1>Reportes <span class="txt-g">ejecutivos</span></h1>
        <p>Resumen generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn" href="{{ route('reportes.csv') }}"><i data-lucide="file-spreadsheet"></i>Exportar CSV</a>
        <button type="button" class="btn btn-primary" data-imprimir><i data-lucide="printer"></i>Imprimir</button>
    </div>
</div>

<div class="grid g-kpi" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px">
    <div class="card kpi" style="--k:var(--g)"><div class="l">Incidencias totales</div><div class="v">{{ $total }}</div><div class="s">Registradas en el sistema</div></div>
    <div class="card kpi" style="--k:var(--r)"><div class="l">Críticas activas</div><div class="v">{{ $datos['kpis']['criticas'] }}</div><div class="s">Atención inmediata</div></div>
    <div class="card kpi" style="--k:var(--c)"><div class="l">Tiempo medio de resolución</div><div class="v">{{ $datos['tiempoMedioHoras'] ?? '—' }}<span style="font-size:14px">{{ $datos['tiempoMedioHoras'] !== null ? ' h' : '' }}</span></div><div class="s">Desde el registro hasta «Resuelta»</div></div>
    <div class="card kpi" style="--k:var(--p)"><div class="l">Confianza media IA</div><div class="v">{{ $datos['confianzaMedia'] }}%</div><div class="s">{{ $datos['corregidas'] }} clasificación(es) corregida(s)</div></div>
</div>

<div class="grid g-3">
    @foreach ($grupos as [$icono, $titulo, $serie, $color])
        <div class="card">
            <div class="card-h"><h3><i data-lucide="{{ $icono }}"></i>{{ $titulo }}</h3></div>
            @foreach ($serie as $i => $s)
                @php $b = $barra($s['etiqueta'], $s['valor'], $total, $color($i, $s)); @endphp
                <div class="bar-row" style="--bc:{{ $b['color'] }}"><span>{{ $b['etiqueta'] }}</span><div class="bar"><i style="width:{{ $b['ancho'] }}%"></i></div><b>{{ $b['n'] }}</b></div>
            @endforeach
        </div>
    @endforeach
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><h3><i data-lucide="table"></i>Detalle de incidencias</h3></div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Código</th><th>Título</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Ubicación</th><th>Técnico</th></tr></thead>
            <tbody>
            @forelse ($incidencias as $i)
                <tr>
                    <td class="mono txt-g"><a href="{{ route('incidencias.show', $i) }}" style="color:inherit">{{ $i->codigo }}</a></td>
                    <td>{{ $i->titulo }}</td>
                    <td>{{ $i->categoria->nombre }}</td>
                    <td><span class="badge {{ $i->prioridad->claseInsignia() }}">{{ $i->prioridad->value }}</span></td>
                    <td><span class="badge {{ $i->estado->clase() }}">{{ $i->estado->value }}</span></td>
                    <td class="muted">{{ $i->ubicacion->pabellon->nombre }} · {{ $i->ubicacion->nombre }}</td>
                    <td class="muted">{{ $i->tecnico?->nombres ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aún no hay incidencias</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['p' => $incidencias])
</div>
@endsection
