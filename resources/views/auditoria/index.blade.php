@extends('layouts.app')

@section('titulo', 'Auditoría')

@php $veIp = auth()->user()->esAdministrador(); @endphp

@section('vista')
<div class="page-h">
    <div>
        <h1>Auditoría <span class="txt-g">del sistema</span></h1>
        <p>Registro automático de cada acción importante realizada en la plataforma.</p>
    </div>
</div>

<div class="card">
    <div class="card-h">
        <h3><i data-lucide="scroll-text"></i>Historial</h3>
        <form method="GET" action="{{ route('auditoria.index') }}" style="display:flex;gap:8px;align-items:center">
            <div class="search-box" style="min-width:min(300px,100%)"><i data-lucide="search"></i>
                <input name="q" maxlength="100" placeholder="Buscar por usuario, acción o detalle…" value="{{ $q }}" aria-label="Buscar en la auditoría"></div>
            <button class="btn btn-sm" type="submit">Buscar</button>
        </form>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Fecha</th><th>Hora</th><th>Usuario</th><th>Rol</th><th>Acción</th><th>Detalle</th>@if ($veIp)<th>IP</th>@endif</tr></thead>
            <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td class="mono">{{ $r->created_at->format('d/m/Y') }}</td>
                    <td class="mono muted">{{ $r->created_at->format('H:i:s') }}</td>
                    <td>{{ $r->actor ?? 'Sistema' }}</td>
                    <td>@if ($r->usuario)<span class="chip">{{ $r->usuario->rol->nombre }}</span>@else<span class="dim">—</span>@endif</td>
                    <td><b>{{ $r->accion }}</b></td>
                    <td class="muted">{{ $r->detalle }}</td>
                    @if ($veIp)<td class="mono muted">{{ $r->ip ?? '—' }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $veIp ? 7 : 6 }}" class="empty">Sin registros</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginacion', ['p' => $registros])
</div>
@endsection
