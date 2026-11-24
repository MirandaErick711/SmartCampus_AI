@extends('layouts.base')

@section('titulo', 'Nueva contraseña')

@section('contenido')
<div class="guest-wrap">
    <div class="card guest-card">
        <div class="brand" style="padding:0 0 16px"><div class="brand-logo"><i data-lucide="cpu"></i></div><div><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <h1 style="font-size:22px;margin-bottom:4px">Elige tu contraseña</h1>
        <p class="muted" style="margin-bottom:18px;font-size:13px">Escribe una contraseña nueva para <b>{{ $email }}</b>. Mínimo 8 caracteres, con letras y números.</p>

        <form method="POST" action="{{ route('password.update') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            @error('email')<div class="note-box" role="alert"><i data-lucide="alert-triangle"></i><div>{{ $message }}</div></div>@enderror
            @error('token')<div class="note-box" role="alert"><i data-lucide="alert-triangle"></i><div>{{ $message }}</div></div>@enderror

            @include('partials.campo', ['id' => 'rs-pass', 'nombre' => 'password', 'etiqueta' => 'Contraseña nueva', 'tipo' => 'password', 'auto' => 'new-password', 'ph' => 'Mín. 8, con letras y números'])
            @include('partials.campo', ['id' => 'rs-pass2', 'nombre' => 'password_confirmation', 'etiqueta' => 'Repetir contraseña', 'tipo' => 'password', 'auto' => 'new-password'])

            <button class="btn btn-primary btn-block" type="submit"><i data-lucide="key-round"></i>Guardar contraseña</button>
            <div class="auth-alt"><a href="{{ route('inicio') }}">Volver al inicio</a></div>
        </form>
    </div>
</div>
@endsection
