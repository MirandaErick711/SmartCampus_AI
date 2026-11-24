@extends('layouts.base')

@php
    $u = auth()->user();
    $u->loadMissing('rol');
    $forzar = $u->debe_cambiar_clave;
    $gestiona = in_array($u->grupo(), ['operador', 'administrador'], true);
    $reporta = in_array($u->grupo(), ['reportante', 'operador', 'administrador'], true);
    $textoBandeja = match ($u->grupo()) { 'reportante' => 'Mis incidencias', 'tecnico' => 'Mis tareas', default => 'Bandeja de incidencias' };
    // [ruta, ícono, texto, visible, patrón para marcar como activo]
    $menu = [
        ['panel',             'layout-dashboard', 'Panel',             true,                    'panel'],
        ['incidencias.create', 'scan-line',       'Reportar incidencia', $reporta,              'incidencias.create'],
        ['incidencias.index', 'inbox',            $textoBandeja,       true,                    'incidencias.index'],
        ['reportes.index',    'bar-chart-3',      'Reportes',          $gestiona,               'reportes.*'],
        ['auditoria.index',   'shield-check',     'Auditoría',         $gestiona,               'auditoria.*'],
        ['ubicaciones.index', 'map-pin',          'Ubicaciones',       $u->esAdministrador(),   'ubicaciones.*'],
        ['usuarios.index',    'users',            'Usuarios y roles',  $u->esAdministrador(),   'usuarios.*'],
        ['cuenta.edit',       'user-cog',         'Mi cuenta',         true,                    'cuenta.*'],
    ];
@endphp

@section('contenido')
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><div class="brand-logo"><i data-lucide="cpu"></i></div><div><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <nav class="nav">
            @foreach ($menu as [$ruta, $icono, $texto, $visible, $patron])
                @if ($visible && (! $forzar || $ruta === 'cuenta.edit'))
                    <a href="{{ route($ruta) }}" class="{{ request()->routeIs($patron) || ($ruta === 'incidencias.index' && request()->routeIs('incidencias.show')) ? 'active' : '' }}">
                        <i data-lucide="{{ $icono }}"></i><span>{{ $texto }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="side-foot">
            <div class="user-card">
                <div class="avatar av-{{ $u->grupo() }}" title="{{ $u->nombres }}">{{ $u->iniciales() }}</div>
                <div style="min-width:0"><div class="n">{{ $u->nombres }}</div><div class="r">{{ $u->rol->nombre }}</div></div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-block btn-danger"><i data-lucide="log-out"></i>Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <div class="overlay" id="overlay"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn-icon burger" id="burger" aria-label="Abrir menú"><i data-lucide="menu"></i></button>
            <h2>@yield('titulo')</h2>
            <div class="sp"></div>
            @unless ($forzar)
                <div class="bell" id="campana" data-url="{{ route('notificaciones.index') }}" data-base="{{ url('/notificaciones') }}"
                     data-inicial="{{ \App\Models\Notificacion::where('user_id', $u->id)->where('leida', false)->count() }}">
                    <button type="button" class="btn btn-icon" id="campana-btn" aria-label="Notificaciones" aria-expanded="false"><i data-lucide="bell"></i><span class="count" id="campana-cuenta" hidden>0</span></button>
                    <div class="notif-panel" id="campana-panel" hidden></div>
                </div>
            @endunless
            <div class="user-chip-top" style="display:flex;align-items:center;gap:8px">
                <div class="avatar av-{{ $u->grupo() }}" title="{{ $u->nombres }}">{{ $u->iniciales() }}</div>
            </div>
        </header>
        <main id="view">
            @yield('vista')
        </main>
    </div>
</div>
@endsection
