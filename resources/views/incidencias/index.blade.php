@extends('layouts.app')

@php
    $grupo = auth()->user()->grupo();
    $titulo = match ($grupo) { 'reportante' => 'Mis incidencias', 'tecnico' => 'Mis tareas asignadas', default => 'Bandeja de incidencias' };
    $sub = match ($grupo) {
        'reportante' => 'Busca por código de ticket y revisa el avance en las 6 fases.',
        'tecnico'    => 'Ordenadas por urgencia: lo pendiente primero.',
        default      => 'Filtra, evalúa, asigna y supervisa el ciclo de vida.',
    };
@endphp

@section('titulo', $titulo)

@section('vista')
<div class="page-h">
    <div><h1>{{ $titulo }}</h1><p>{{ $sub }}</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span class="chip"><i data-lucide="list"></i>{{ $incidencias->total() }} resultado(s)</span>
        @if ($grupo !== 'tecnico')
            <a class="btn btn-primary" href="{{ route('incidencias.create') }}"><i data-lucide="plus"></i>Nueva incidencia</a>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('incidencias.index') }}" class="card" style="margin-bottom:14px">
    <div class="filters">
        <div class="field span2"><label for="fl-q">Buscar</label>
            <div class="search-box"><i data-lucide="search"></i><input id="fl-q" name="q" maxlength="100" placeholder="Código (INC-2026-0001), título o texto…" value="{{ $filtros['q'] ?? '' }}"></div>
        </div>
        <div class="field"><label for="fl-e">Estado</label>
            <select id="fl-e" name="estado" data-autosubmit><option value="">Todos</option>
                @foreach (\App\Enums\EstadoIncidencia::cases() as $e)<option value="{{ $e->value }}" @selected(($filtros['estado'] ?? '') === $e->value)>{{ $e->value }}</option>@endforeach
            </select></div>
        <div class="field"><label for="fl-p">Prioridad</label>
            <select id="fl-p" name="prioridad" data-autosubmit><option value="">Todas</option>
                @foreach (\App\Enums\Prioridad::cases() as $p)<option value="{{ $p->value }}" @selected(($filtros['prioridad'] ?? '') === $p->value)>{{ $p->value }}</option>@endforeach
            </select></div>
        <div class="field"><label for="fl-c">Categoría</label>
            <select id="fl-c" name="categoria" data-autosubmit><option value="">Todas</option>
                @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected((int) ($filtros['categoria'] ?? 0) === $c->id)>{{ $c->nombre }}</option>@endforeach
            </select></div>
        <div class="field"><label for="fl-u">Pabellón</label>
            <select id="fl-u" name="pabellon" data-autosubmit><option value="">Todos</option>
                @foreach ($pabellones as $p)<option value="{{ $p->id }}" @selected((int) ($filtros['pabellon'] ?? 0) === $p->id)>{{ $p->nombre }}</option>@endforeach
            </select></div>
        <div style="display:flex;gap:6px;height:42px">
            <button class="btn btn-sm" type="submit"><i data-lucide="search"></i>Buscar</button>
            <a class="btn btn-sm" href="{{ route('incidencias.index') }}" title="Quitar filtros"><i data-lucide="filter-x"></i></a>
        </div>
    </div>
</form>

@forelse ($incidencias as $inc)
    @php $foto = $inc->evidencias->firstWhere('tipo', 'reporte'); @endphp
    <a href="{{ route('incidencias.show', $inc) }}" class="ticket card {{ $inc->prioridad->claseBorde() }}" style="color:inherit;text-decoration:none">
        <div class="thumb">
            @if ($foto)<img src="{{ route('evidencias.ver', $foto) }}" alt="" loading="lazy">@else<i data-lucide="image-off"></i>@endif
        </div>
        <div style="min-width:0">
            <div class="code mono">{{ $inc->codigo }}@if ($inc->estado->enCurso()) <span class="pulse-dot" style="margin-left:6px"></span>@endif</div>
            <div class="tt">{{ $inc->titulo }}</div>
            <div class="meta">
                <span class="badge {{ $inc->prioridad->claseInsignia() }}">{{ $inc->prioridad->value }}</span>
                <span class="chip"><i data-lucide="tag"></i>{{ $inc->categoria->nombre }}</span>
                <span class="chip"><i data-lucide="map-pin"></i>{{ $inc->ubicacion->pabellon->nombre }} · {{ $inc->ubicacion->nombre }}</span>
                @if ($inc->tecnico)<span class="chip" style="color:var(--c)"><i data-lucide="hard-hat"></i>{{ $inc->tecnico->nombres }}</span>@endif
                <span class="dim" style="font-size:11px">{{ $inc->created_at->format('d/m/Y H:i') }}</span>
            </div>
        </div>
        <div class="side">
            <span class="badge {{ $inc->estado->clase() }}">{{ $inc->estado->value }}</span>
            <div class="mini-prog">@for ($k = 0; $k < 6; $k++)<i class="{{ $k <= $inc->estado->paso() ? 'on' : '' }}"></i>@endfor</div>
            <span class="btn btn-sm"><i data-lucide="eye"></i>{{ in_array($grupo, ['operador', 'administrador'], true) ? 'Gestionar' : 'Detalle' }}</span>
        </div>
        @if ($grupo === 'reportante')
            <div class="full-tl">@include('incidencias._linea_tiempo', ['inc' => $inc, 'compacta' => true])</div>
        @endif
    </a>
@empty
    <div class="card empty"><i data-lucide="search-x"></i><div>No se encontraron incidencias con los filtros actuales.</div></div>
@endforelse

@include('partials.paginacion', ['p' => $incidencias])
@endsection
