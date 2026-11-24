@extends('layouts.app')

@section('titulo', 'Panel')

@php
    $grupo = $usuario->grupo();
    $gestiona = in_array($grupo, ['operador', 'administrador'], true);
    $k = $datos['kpis'];
    $tarjetas = [
        ['Total de incidencias', $k['total'], $grupo === 'reportante' ? 'Reportadas por ti' : ($grupo === 'tecnico' ? 'Asignadas a ti' : 'En todo el campus'), 'var(--g)', 'layers', ''],
        ['Sin asignar', $k['sinAsignar'], 'Pendientes de gestión', 'var(--a)', 'hourglass', ''],
        ['En atención', $k['enAtencion'], 'Con técnico asignado', 'var(--c)', 'wrench', ''],
        ['Críticas activas', $k['criticas'], 'Requieren acción inmediata', 'var(--r)', 'flame', ''],
        ['Resueltas / Cerradas', $k['resueltas'], 'Ciclo completado', 'var(--g)', 'check-circle', ''],
        ['Tasa de resolución', $k['tasa'], '% del total', 'var(--p)', 'gauge', '%'],
    ];
    $maxCarga = max(1, (int) $tecnicos->max('activos'));
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js"></script>
<script src="{{ asset('js/panel.js') }}" defer></script>
@endpush

@section('vista')
<script type="application/json" id="datos-panel">{!! json_encode(['total' => $datos['total'], 'estados' => $datos['estados'], 'categorias' => $datos['categorias'], 'pabellones' => $datos['pabellones']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

<div class="page-h">
    <div>
        <h1>Hola, <span class="txt-g">{{ \Illuminate\Support\Str::before($usuario->nombres, ' ') }}</span></h1>
        <p>Rol: {{ $usuario->rol->nombre }}@if ($usuario->departamento) · {{ $usuario->departamento }}@endif · {{ now()->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>
    @if ($grupo !== 'tecnico')
        <a class="btn btn-primary" href="{{ route('incidencias.create') }}"><i data-lucide="plus"></i>Nueva incidencia</a>
    @else
        <a class="btn btn-cyan" href="{{ route('incidencias.index') }}"><i data-lucide="inbox"></i>Ver mis tareas</a>
    @endif
</div>

@if ($resumen)
    <div class="grid g-kpi" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px">
        <div class="card kpi" style="--k:var(--g)"><div class="l">Cuentas activas<i data-lucide="user-check"></i></div><div class="v">{{ $resumen['activas'] }}</div></div>
        <div class="card kpi" style="--k:var(--a)"><div class="l">Por aprobar<i data-lucide="hourglass"></i></div><div class="v">{{ $resumen['pendientes'] }}</div></div>
        <div class="card kpi" style="--k:var(--c)"><div class="l">Invitaciones<i data-lucide="mail"></i></div><div class="v">{{ $resumen['invitadas'] }}</div></div>
        <div class="card kpi" style="--k:var(--dim)"><div class="l">Desactivadas<i data-lucide="user-x"></i></div><div class="v">{{ $resumen['inactivas'] }}</div></div>
    </div>
    @if ($resumen['pendientes'] > 0)
        <div class="note-box"><i data-lucide="hourglass"></i><div>Hay {{ $resumen['pendientes'] }} solicitud(es) de cuenta esperando tu aprobación. <a href="{{ route('usuarios.index') }}">Revisarlas</a></div></div>
    @endif
@endif

<div class="grid g-kpi" style="margin-bottom:16px">
    @foreach ($tarjetas as [$etiqueta, $valor, $sub, $color, $icono, $sufijo])
        <div class="card kpi" style="--k:{{ $color }}">
            <div class="l">{{ $etiqueta }}<i data-lucide="{{ $icono }}"></i></div>
            <div class="v"><span data-count="{{ $valor }}">{{ $valor }}</span>{{ $sufijo }}</div>
            <div class="s">{{ $sub }}</div>
        </div>
    @endforeach
</div>

<div class="grid g-3" style="margin-bottom:16px">
    <div class="card"><div class="card-h"><h3><i data-lucide="pie-chart"></i>Por categoría</h3></div><div class="chart-box"><canvas id="gr-categoria" role="img" aria-label="Incidencias por categoría"></canvas></div></div>
    <div class="card"><div class="card-h"><h3><i data-lucide="map-pin"></i>Pabellones con más incidencias</h3></div><div class="chart-box"><canvas id="gr-pabellon" role="img" aria-label="Incidencias por pabellón"></canvas></div></div>
    <div class="card"><div class="card-h"><h3><i data-lucide="activity"></i>Distribución por estado</h3></div><div class="chart-box"><canvas id="gr-estado" role="img" aria-label="Incidencias por estado"></canvas></div></div>
</div>

<div class="grid g-21">
    <div class="card">
        <div class="card-h"><h3><i data-lucide="clock"></i>Actividad reciente</h3><a class="btn btn-sm" href="{{ route('incidencias.index') }}">Ver todas</a></div>
        @forelse ($recientes as $r)
            <a href="{{ route('incidencias.show', $r) }}" class="pend-row">
                <div style="min-width:0">
                    <div class="mono txt-g" style="font-size:11px;font-weight:700">{{ $r->codigo }}</div>
                    <div style="font-weight:600">{{ $r->titulo }}</div>
                    <div class="muted" style="font-size:12px">{{ $r->ubicacion->pabellon->nombre }} · {{ $r->ubicacion->nombre }} · {{ $r->updated_at->diffForHumans() }}</div>
                </div>
                <span class="badge {{ $r->estado->clase() }}">{{ $r->estado->value }}</span>
            </a>
        @empty
            <div class="empty"><i data-lucide="inbox"></i><div>{{ $grupo === 'tecnico' ? 'Aún no tienes tareas asignadas.' : 'Todavía no hay incidencias. ¡Reporta la primera!' }}</div></div>
        @endforelse
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card" @if ($criticas->isNotEmpty()) style="border-color:rgba(255,42,95,.45);box-shadow:var(--glow-r)" @endif>
            <div class="card-h"><h3><i data-lucide="siren" style="color:var(--r)"></i>Alertas críticas</h3>@if ($criticas->isNotEmpty())<span class="pulse-dot"></span>@endif</div>
            @forelse ($criticas as $c)
                <a href="{{ route('incidencias.show', $c) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--border);color:inherit;text-decoration:none">
                    <div class="mono txt-r" style="font-size:11px;font-weight:700">{{ $c->codigo }}</div>
                    <div style="font-size:13px">{{ $c->titulo }}</div>
                    <div class="muted" style="font-size:11px">{{ $c->ubicacion->pabellon->nombre }} · {{ $c->ubicacion->nombre }} · {{ $c->estado->value }}</div>
                </a>
            @empty
                <div class="muted" style="font-size:13px">Sin alertas críticas activas ✔</div>
            @endforelse
        </div>

        @if ($grupo !== 'reportante')
            <div class="card">
                <div class="card-h"><h3><i data-lucide="hard-hat" style="color:var(--c)"></i>Carga de técnicos</h3></div>
                @forelse ($tecnicos as $t)
                    <div class="bar-row" style="--bc:var(--c)"><span title="{{ $t->departamento }}">{{ \Illuminate\Support\Str::limit($t->nombres, 16) }}</span><div class="bar"><i style="width:{{ $t->activos / $maxCarga * 100 }}%"></i></div><b>{{ $t->activos }}</b></div>
                @empty
                    <div class="muted" style="font-size:13px">Sin técnicos activos</div>
                @endforelse
            </div>
        @endif
    </div>
</div>
@endsection
