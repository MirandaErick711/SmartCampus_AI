@extends('layouts.app')

@section('titulo', 'Usuarios y roles')

@php
    $yo = auth()->id();
    $abrir = $errors->getBag('invitar')->any() ? 'invitar' : ($errors->getBag('relevo')->any() ? 'relevo' : '');
    $colorGrupo = ['reportante' => 'var(--g)', 'operador' => 'var(--a)', 'tecnico' => 'var(--c)', 'administrador' => 'var(--p)'];
@endphp

@section('vista')
<div id="estado-inicial" data-modal="{{ $abrir }}" hidden></div>

<div class="page-h">
    <div>
        <h1>Usuarios y <span class="txt-p">roles</span></h1>
        <p>Aprueba solicitudes, invita personas, asigna roles y activa o desactiva cuentas.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" data-abrir="invitar"><i data-lucide="user-plus"></i>Invitar usuario</button>
        <button type="button" class="btn btn-amber" data-abrir="relevo"><i data-lucide="crown"></i>Nuevo administrador</button>
    </div>
</div>

{{-- ===== Relevo de administrador en curso ===== --}}
@if ($relevo)
    <div class="card pend-box">
        <div class="card-h"><h3><i data-lucide="crown"></i>Relevo de administrador en curso</h3></div>
        <p class="muted" style="font-size:13px;line-height:1.6">
            Invitaste a <b>{{ $relevo->nombres }}</b> (<span class="mono">{{ $relevo->email }}</span>). Cuando active su cuenta,
            <b>tu cuenta de administrador se cerrará para siempre</b>.
        </p>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
            <form method="POST" action="{{ route('usuarios.invitacion', $relevo) }}">@csrf
                <button class="btn btn-sm" type="submit"><i data-lucide="send"></i>Reenviar invitación</button>
            </form>
            <form method="POST" action="{{ route('usuarios.destroy', $relevo) }}" data-confirmar="¿Cancelar la invitación a {{ $relevo->nombres }}?">@csrf @method('DELETE')
                <button class="btn btn-sm btn-danger" type="submit"><i data-lucide="x"></i>Cancelar relevo</button>
            </form>
        </div>
    </div>
@endif

{{-- ===== Solicitudes pendientes ===== --}}
@if ($pendientes->isNotEmpty())
    <div class="card pend-box">
        <div class="card-h"><h3><i data-lucide="hourglass"></i>Solicitudes pendientes de aprobación <span class="badge e-En-evaluación">{{ $pendientes->count() }}</span></h3></div>
        @foreach ($pendientes as $p)
            <div class="pend-row">
                <div style="display:flex;gap:10px;align-items:center;min-width:0">
                    <div class="avatar av-{{ $p->grupo() }}">{{ $p->iniciales() }}</div>
                    <div style="min-width:0">
                        <div style="font-weight:600">{{ $p->nombres }} <span class="chip">{{ $p->rol->nombre }}</span></div>
                        <div class="muted mono" style="font-size:11px">{{ $p->email }}@if ($p->departamento) · {{ $p->departamento }}@endif
                            @unless ($p->hasVerifiedEmail()) · <span class="txt-a">correo sin confirmar</span>@endunless
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <form method="POST" action="{{ route('usuarios.aprobar', $p) }}">@csrf
                        <button class="btn btn-primary btn-sm" type="submit"><i data-lucide="check"></i>Aprobar</button>
                    </form>
                    <form method="POST" action="{{ route('usuarios.destroy', $p) }}" data-confirmar="¿Rechazar la solicitud de {{ $p->nombres }}?">@csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm" type="submit"><i data-lucide="x"></i>Rechazar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- ===== Resumen por rol ===== --}}
<div class="grid g-kpi" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-bottom:16px">
    @foreach ($porRol as $r)
        <div class="card kpi" style="--k:{{ $colorGrupo[$r->grupo] ?? 'var(--g)' }}">
            <div class="l">{{ $r->nombre }}</div>
            <div class="v">{{ $r->activos }}</div>
            <div class="s">activa(s)</div>
        </div>
    @endforeach
</div>

{{-- ===== Tabla ===== --}}
<div class="card">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Usuario</th><th>Rol</th><th>Departamento</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            @foreach ($usuarios as $x)
                @php
                    $esYo = $x->id === $yo;
                    $esAdmin = $x->esAdministrador();
                    [$textoEstado, $claseEstado] = $x->etiquetaEstado();
                    $editable = ! $esYo && ! $esAdmin && in_array($x->estado, ['activa', 'inactiva'], true);
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;gap:10px;align-items:center">
                            <div class="avatar av-{{ $x->grupo() }}">{{ $x->iniciales() }}</div>
                            <div>
                                <div style="font-weight:600">{{ $x->nombres }} @if ($esYo)<span class="chip" style="font-size:10px">tú</span>@endif</div>
                                <div class="muted mono" style="font-size:11px">{{ $x->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($editable)
                            <form method="POST" action="{{ route('usuarios.rol', $x) }}">@csrf @method('PATCH')
                                <select name="rol_id" data-autosubmit aria-label="Rol de {{ $x->nombres }}">
                                    @foreach ($roles as $r)
                                        <option value="{{ $r->id }}" @selected($x->rol_id === $r->id)>{{ $r->nombre }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <span class="chip">{{ $x->rol->nombre }}</span>
                        @endif
                    </td>
                    <td class="muted">{{ $x->departamento ?: '—' }}</td>
                    <td>
                        @if ($editable)
                            <form method="POST" action="{{ route('usuarios.estado', $x) }}" style="display:flex;align-items:center;gap:8px">@csrf @method('PATCH')
                                <label class="switch" title="Activar o desactivar la cuenta">
                                    <input type="checkbox" data-autosubmit @checked($x->estado === 'activa') aria-label="Cuenta activa de {{ $x->nombres }}"><span></span>
                                </label>
                            </form>
                        @else
                            <span class="badge {{ $claseEstado }}">{{ $textoEstado }}</span>
                        @endif
                        @unless ($x->hasVerifiedEmail())
                            <div class="txt-a" style="font-size:11px;margin-top:4px">correo sin confirmar</div>
                        @endunless
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            @if ($x->estado === 'activa' && ! $esYo)
                                <form method="POST" action="{{ route('usuarios.restablecer', $x) }}" data-confirmar="¿Enviar a {{ $x->nombres }} un enlace para restablecer su contraseña?">@csrf
                                    <button class="btn btn-sm btn-icon" type="submit" title="Enviar enlace para restablecer contraseña" aria-label="Enviar enlace de recuperación"><i data-lucide="key-round"></i></button>
                                </form>
                            @endif
                            @if ($x->estado === 'invitada')
                                <form method="POST" action="{{ route('usuarios.invitacion', $x) }}">@csrf
                                    <button class="btn btn-sm btn-icon" type="submit" title="Reenviar invitación" aria-label="Reenviar invitación"><i data-lucide="send"></i></button>
                                </form>
                                <form method="POST" action="{{ route('usuarios.destroy', $x) }}" data-confirmar="¿Cancelar la invitación a {{ $x->nombres }}?">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-icon btn-danger" type="submit" title="Cancelar invitación" aria-label="Cancelar invitación"><i data-lucide="trash-2"></i></button>
                                </form>
                            @endif
                            @if ($esYo || ($x->estado !== 'activa' && $x->estado !== 'invitada'))
                                <span class="dim">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ================= CUADRO: INVITAR USUARIO ================= --}}
<div class="modal-back" id="modal-invitar" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-invitar">
        <div class="modal-h">
            <div><h3 id="t-invitar">Invitar usuario</h3><div class="muted" style="font-size:12px">Recibirá un correo para crear su contraseña</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('usuarios.invitar') }}" autocomplete="off">@csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'iv-nombres', 'nombre' => 'nombres', 'etiqueta' => 'Nombre completo', 'bolsa' => 'invitar'])
                @include('partials.campo', ['id' => 'iv-email', 'nombre' => 'email', 'etiqueta' => 'Correo', 'tipo' => 'email', 'bolsa' => 'invitar', 'ph' => 'persona@gmail.com'])
                <div class="field">
                    <label for="iv-rol">Rol *</label>
                    <select id="iv-rol" name="rol_id" required>
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}" @selected((int) old('rol_id') === $r->id)>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                    @error('rol_id', 'invitar')<div class="field-err" role="alert">{{ $message }}</div>@enderror
                </div>
                @include('partials.campo', ['id' => 'iv-depto', 'nombre' => 'departamento', 'etiqueta' => 'Departamento o especialidad', 'bolsa' => 'invitar', 'requerido' => false])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="send"></i>Enviar invitación</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= CUADRO: NUEVO ADMINISTRADOR (RELEVO) ================= --}}
<div class="modal-back" id="modal-relevo" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-relevo">
        <div class="modal-h">
            <div><h3 id="t-relevo">Nuevo administrador</h3><div class="muted" style="font-size:12px">Traspaso del mando del sistema</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('usuarios.relevo') }}" autocomplete="off">@csrf
            <div class="modal-b">
                <div class="note-box"><i data-lucide="alert-triangle"></i><div>Solo puede haber <b>un administrador</b>. Cuando esta persona active su cuenta, <b>la tuya se cerrará para siempre</b> y perderás el acceso.</div></div>
                @include('partials.campo', ['id' => 'rl-nombres', 'nombre' => 'nombres', 'etiqueta' => 'Nombre completo', 'bolsa' => 'relevo'])
                @include('partials.campo', ['id' => 'rl-email', 'nombre' => 'email', 'etiqueta' => 'Correo del nuevo administrador', 'tipo' => 'email', 'bolsa' => 'relevo', 'ph' => 'nuevo.admin@gmail.com'])
                @include('partials.campo', ['id' => 'rl-clave', 'nombre' => 'clave_actual', 'etiqueta' => 'Tu contraseña (para confirmar)', 'tipo' => 'password', 'bolsa' => 'relevo', 'auto' => 'current-password'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="crown"></i>Enviar invitación de relevo</button>
            </div>
        </form>
    </div>
</div>
@endsection
