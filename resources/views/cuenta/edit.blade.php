@extends('layouts.app')

@section('titulo', 'Mi cuenta')

@section('vista')
<div class="page-h">
    <div><h1>Mi <span class="txt-p">cuenta</span></h1><p>Tus datos, tu contraseña y tu correo.</p></div>
</div>

@if ($usuario->debe_cambiar_clave)
    <div class="note-box"><i data-lucide="shield-alert"></i><div>Por seguridad debes <b>cambiar tu contraseña temporal</b> para continuar usando el sistema.</div></div>
@endif

<div class="grid g-2" style="align-items:start">
    {{-- ===== Contraseña ===== --}}
    <div class="card">
        <div class="card-h"><h3><i data-lucide="key-round"></i>Cambiar contraseña</h3></div>
        <form method="POST" action="{{ route('cuenta.clave') }}" autocomplete="off">
            @csrf @method('PUT')
            @include('partials.campo', ['id' => 'cl-actual', 'nombre' => 'clave_actual', 'etiqueta' => 'Contraseña actual', 'tipo' => 'password', 'bolsa' => 'clave', 'auto' => 'current-password'])
            @include('partials.campo', ['id' => 'cl-nueva', 'nombre' => 'password', 'etiqueta' => 'Contraseña nueva', 'tipo' => 'password', 'bolsa' => 'clave', 'auto' => 'new-password', 'ph' => 'Mín. 8, con letras y números'])
            @include('partials.campo', ['id' => 'cl-nueva2', 'nombre' => 'password_confirmation', 'etiqueta' => 'Repetir contraseña nueva', 'tipo' => 'password', 'bolsa' => 'clave', 'auto' => 'new-password'])
            <button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Guardar contraseña</button>
        </form>
    </div>

    @unless ($usuario->debe_cambiar_clave)
        {{-- ===== Datos personales ===== --}}
        <div class="card">
            <div class="card-h"><h3><i data-lucide="user"></i>Mis datos</h3></div>
            <form method="POST" action="{{ route('cuenta.perfil') }}">
                @csrf @method('PUT')
                @include('partials.campo', ['id' => 'pf-nombres', 'nombre' => 'nombres', 'etiqueta' => 'Nombre completo', 'bolsa' => 'perfil', 'valor' => old('nombres', $usuario->nombres)])
                @include('partials.campo', ['id' => 'pf-depto', 'nombre' => 'departamento', 'etiqueta' => 'Departamento o especialidad', 'bolsa' => 'perfil', 'requerido' => false, 'valor' => old('departamento', $usuario->departamento)])
                <div class="field"><label>Rol</label><div><span class="chip">{{ $usuario->rol->nombre }}</span></div></div>
                <button class="btn" type="submit"><i data-lucide="save"></i>Guardar datos</button>
            </form>
        </div>

        {{-- ===== Correo ===== --}}
        <div class="card">
            <div class="card-h"><h3><i data-lucide="mail"></i>Cambiar correo</h3></div>
            <p class="muted" style="font-size:13px;margin-bottom:12px">Correo actual: <b class="mono">{{ $usuario->email }}</b></p>
            @if ($usuario->email_pendiente)
                <div class="note-box"><i data-lucide="clock"></i><div>Esperando confirmación de <b>{{ $usuario->email_pendiente }}</b>. Abre el enlace que enviamos a ese correo.</div></div>
            @endif
            <form method="POST" action="{{ route('cuenta.correo') }}" autocomplete="off">
                @csrf
                @include('partials.campo', ['id' => 'ce-email', 'nombre' => 'email', 'etiqueta' => 'Correo nuevo', 'tipo' => 'email', 'bolsa' => 'correo', 'auto' => 'off', 'ph' => 'nuevo@gmail.com'])
                @include('partials.campo', ['id' => 'ce-clave', 'nombre' => 'clave_actual', 'etiqueta' => 'Tu contraseña actual', 'tipo' => 'password', 'bolsa' => 'correo', 'auto' => 'current-password'])
                <button class="btn" type="submit"><i data-lucide="send"></i>Enviar confirmación</button>
            </form>
        </div>
    @endunless
</div>
@endsection
