@extends('layouts.base')

@section('titulo', 'Gestión inteligente de incidencias universitarias')

@php
    $pasos = [
        ['camera', 'Reporta con evidencia', 'Describe el desperfecto, indica tu ubicación en el campus y adjunta una fotografía del problema.', ''],
        ['sparkles', 'Análisis por IA', 'El sistema procesa el texto y la imagen al instante para proponer la categoría, subcategoría y nivel de prioridad.', 'ai'],
        ['clipboard-check', 'Evaluación y asignación', 'El operador revisa el diagnóstico automático y asigna la tarea al técnico disponible.', ''],
        ['wrench', 'Atención en sitio', 'El técnico acude al lugar, repara el desperfecto y actualiza el estado de la incidencia.', ''],
        ['activity', 'Seguimiento en vivo', 'Consulta el progreso de tu ticket en tiempo real hasta su solución final.', 'ok'],
    ];
    $estados = ['Registrada', 'En evaluación', 'Asignada', 'En atención', 'Resuelta', 'Cerrada'];
@endphp

@section('contenido')
{{-- Indica a app.js qué cuadro abrir al cargar (por errores, mensajes o enlaces de correo) --}}
<div id="estado-inicial" data-modal="{{ $abrir }}" hidden></div>

<div class="land">
    <div class="orb" style="width:420px;height:420px;background:var(--g);top:-150px;left:-150px"></div>
    <div class="orb" style="width:380px;height:380px;background:var(--p);top:60px;right:-140px"></div>

    <header class="land-nav">
        <div class="brand" style="padding:0"><div class="brand-logo"><i data-lucide="cpu"></i></div><div><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <button type="button" class="btn btn-primary" data-abrir="login"><i data-lucide="log-in"></i>Acceder</button>
    </header>

    <section class="land-hero">
        <h1>Gestión <span>inteligente</span> de incidencias universitarias</h1>
        <p>Reporta, clasifica y resuelve incidencias del campus con un motor de IA multimodal que analiza texto, evidencia fotográfica y contexto para sugerir categoría, prioridad y nivel de confianza. Conectamos a docentes, administrativos y personal técnico en una sola plataforma para atender al instante desperfectos de infraestructura, tecnología, mobiliario y servicios, manteniendo cada aula y laboratorio siempre operativo.</p>
        <div class="land-cta"><button type="button" class="btn btn-primary" data-abrir="login" style="padding:12px 26px;font-size:14px"><i data-lucide="log-in"></i>Acceder</button></div>
    </section>

    <section class="land-sec">
        <h2><i data-lucide="layout-grid"></i>¿Qué hace la plataforma?</h2>
        <div class="land-grid">
            <div class="card hover land-feat"><div class="ic ic-p"><i data-lucide="scan-search"></i></div><h3>Diagnóstico Inteligente</h3><p>Combina el análisis de tu descripción y la foto adjunta para detectar el problema y priorizar las urgencias automáticamente.</p></div>
            <div class="card hover land-feat"><div class="ic ic-c"><i data-lucide="user-check"></i></div><h3>Coordinación Ágil</h3><p>Permite a los operadores validar la sugerencia de la IA en segundos y derivar el caso al técnico especialista indicado.</p></div>
            <div class="card hover land-feat"><div class="ic"><i data-lucide="bar-chart-3"></i></div><h3>Control y Estadísticas</h3><p>Monitorea cada ticket en vivo a través de 6 estados claros e identifica los pabellones o áreas con mayor índice de fallas.</p></div>
        </div>
    </section>

    <section class="land-sec">
        <h2><i data-lucide="route"></i>¿Cómo funciona?</h2>
        <div class="steps">
            @foreach ($pasos as $i => [$icono, $titulo, $texto, $clase])
                <div class="card hover step {{ $clase }}">
                    <div class="step-n">{{ $i + 1 }}</div>
                    <div class="step-ic"><i data-lucide="{{ $icono }}"></i></div>
                    <h3>{{ $titulo }}</h3>
                    <p>{{ $texto }}</p>
                    @if ($i === 4)
                        <div class="state-flow">
                            @foreach ($estados as $k => $estado)
                                <span class="badge e-{{ str_replace(' ', '-', $estado) }}">{{ $estado }}</span>
                                @if ($k < 5)<i data-lucide="chevron-right"></i>@endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <footer class="land-foot">
        <span>© {{ date('Y') }} SmartCampus AI · Gestión inteligente de incidencias universitarias</span>
        <button type="button" class="btn btn-sm" data-abrir="login"><i data-lucide="log-in"></i>Acceder</button>
    </footer>
</div>

{{-- ================= CUADRO: INICIAR SESIÓN ================= --}}
<div class="modal-back" id="modal-login" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-login">
        <div class="modal-h">
            <div><h3 id="t-login">Iniciar sesión</h3><div class="muted" style="font-size:12px">Accede con tu correo y contraseña</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('ingresar') }}" autocomplete="on">
            @csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'lg-email', 'nombre' => 'email', 'etiqueta' => 'Usuario (correo)', 'tipo' => 'email', 'bolsa' => 'login', 'auto' => 'username', 'ph' => 'usuario@gmail.com'])
                @include('partials.campo', ['id' => 'lg-pass', 'nombre' => 'password', 'etiqueta' => 'Contraseña', 'tipo' => 'password', 'bolsa' => 'login', 'auto' => 'current-password', 'ph' => '••••••••'])
                <label class="check"><input type="checkbox" name="recordar" value="1"> Mantener la sesión iniciada</label>
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="log-in"></i>Ingresar</button>
                <div class="auth-alt">¿No tienes cuenta? <a href="#" data-abrir="registro">Registrarse</a></div>
                <div class="auth-alt auth-forgot"><a href="#" data-abrir="olvide">¿Olvidaste tu contraseña?</a></div>
                <div class="auth-alt auth-forgot"><a href="#" data-abrir="reenviar">¿No te llegó el correo de confirmación?</a></div>
            </div>
        </form>
    </div>
</div>

{{-- ================= CUADRO: REGISTRARSE ================= --}}
<div class="modal-back" id="modal-registro" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-registro">
        <div class="modal-h">
            <div><h3 id="t-registro">Crear cuenta</h3><div class="muted" style="font-size:12px">Usa un correo que puedas abrir: ahí confirmarás tu cuenta</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('registro') }}" autocomplete="off">
            @csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'rg-nombres', 'nombre' => 'nombres', 'etiqueta' => 'Nombre completo', 'bolsa' => 'registro', 'auto' => 'name', 'ph' => 'Nombres y apellidos'])
                @include('partials.campo', ['id' => 'rg-email', 'nombre' => 'email', 'etiqueta' => 'Correo', 'tipo' => 'email', 'bolsa' => 'registro', 'auto' => 'email', 'ph' => 'tucorreo@gmail.com'])
                <div class="field">
                    <label for="rg-rol">Registrarse como *</label>
                    <select id="rg-rol" name="rol" required>
                        <option value="">Selecciona tu rol…</option>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->slug }}" @selected(old('rol') === $rol->slug)>{{ $rol->nombre }}{{ $rol->requiere_aprobacion ? ' (requiere aprobación)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('rol', 'registro')<div class="field-err" role="alert">{{ $message }}</div>@enderror
                </div>
                @include('partials.campo', ['id' => 'rg-depto', 'nombre' => 'departamento', 'etiqueta' => 'Departamento o especialidad', 'bolsa' => 'registro', 'requerido' => false, 'ph' => 'Opcional'])
                @include('partials.campo', ['id' => 'rg-pass', 'nombre' => 'password', 'etiqueta' => 'Contraseña', 'tipo' => 'password', 'bolsa' => 'registro', 'auto' => 'new-password', 'ph' => 'Mín. 8, con letras y números'])
                @include('partials.campo', ['id' => 'rg-pass2', 'nombre' => 'password_confirmation', 'etiqueta' => 'Repetir contraseña', 'tipo' => 'password', 'bolsa' => 'registro', 'auto' => 'new-password'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="user-plus"></i>Crear cuenta</button>
                <div class="auth-alt">¿Ya tienes cuenta? <a href="#" data-abrir="login">Iniciar sesión</a></div>
            </div>
        </form>
    </div>
</div>

{{-- ================= CUADRO: OLVIDÉ MI CONTRASEÑA ================= --}}
<div class="modal-back" id="modal-olvide" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-olvide">
        <div class="modal-h">
            <div><h3 id="t-olvide">Recuperar contraseña</h3><div class="muted" style="font-size:12px">Te enviaremos un enlace a tu correo</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'fg-email', 'nombre' => 'email', 'etiqueta' => 'Correo de tu cuenta', 'tipo' => 'email', 'bolsa' => 'olvide', 'auto' => 'email', 'ph' => 'tucorreo@gmail.com'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="mail"></i>Enviar enlace</button>
                <div class="auth-alt"><a href="#" data-abrir="login">Volver a iniciar sesión</a></div>
            </div>
        </form>
    </div>
</div>

{{-- ================= CUADRO: REENVIAR CONFIRMACIÓN ================= --}}
<div class="modal-back" id="modal-reenviar" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-reenviar">
        <div class="modal-h">
            <div><h3 id="t-reenviar">Reenviar confirmación</h3><div class="muted" style="font-size:12px">Te enviaremos un nuevo enlace para confirmar tu correo</div></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button>
        </div>
        <form method="POST" action="{{ route('correo.reenviar') }}">
            @csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'rv-email', 'nombre' => 'email', 'etiqueta' => 'Correo con el que te registraste', 'tipo' => 'email', 'bolsa' => 'reenviar', 'auto' => 'email', 'ph' => 'tucorreo@gmail.com'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="send"></i>Reenviar enlace</button>
                <div class="auth-alt"><a href="#" data-abrir="login">Volver a iniciar sesión</a></div>
            </div>
        </form>
    </div>
</div>
@endsection
