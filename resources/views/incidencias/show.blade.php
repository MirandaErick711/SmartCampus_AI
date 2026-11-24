@extends('layouts.app')

@section('titulo', $inc->codigo)

@section('vista')
@php $ia = $inc->analisis; $fotoReporte = $inc->evidenciaDe('reporte'); $fotoSolucion = $inc->evidenciaDe('solucion'); @endphp

<script type="application/json" id="datos-categorias">{!! json_encode($categorias->map(fn ($c) => ['id' => $c->id, 'subs' => $c->subcategorias->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()])->values(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

<div class="page-h">
    <div style="min-width:0">
        <div class="mono txt-g" style="font-size:12px;font-weight:700">{{ $inc->codigo }}@if ($inc->estado->enCurso()) <span class="pulse-dot" style="margin-left:6px"></span>@endif</div>
        <h1 style="font-size:24px">{{ $inc->titulo }}</h1>
    </div>
    <a class="btn" href="{{ route('incidencias.index') }}"><i data-lucide="arrow-left"></i>Volver</a>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <span class="badge {{ $inc->estado->clase() }}">{{ $inc->estado->value }}</span>
    <span class="badge {{ $inc->prioridad->claseInsignia() }}">{{ $inc->prioridad->value }}</span>
    <span class="chip"><i data-lucide="tag"></i>{{ $inc->categoria->nombre }} · {{ $inc->subcategoria?->nombre ?? 'sin subcategoría' }}</span>
</div>

<div class="card" style="padding:16px 12px;margin-bottom:16px">@include('incidencias._linea_tiempo', ['inc' => $inc])</div>

<div class="det-grid">
    <div>
        <div class="sec" style="margin-top:0"><h4><i data-lucide="align-left"></i>Descripción</h4>
            <p style="font-size:13.5px;color:#d5dae4;white-space:pre-line">{{ $inc->descripcion }}</p></div>

        <div class="sec"><h4><i data-lucide="info"></i>Información</h4>
            <dl class="kv">
                <dt>Ubicación</dt><dd>{{ $inc->ubicacion->pabellon->campus->nombre }} › {{ $inc->ubicacion->pabellon->nombre }} › Piso {{ $inc->ubicacion->piso }} › {{ $inc->ubicacion->nombre }}</dd>
                <dt>Área afectada</dt><dd>{{ $inc->area->nombre }}</dd>
                <dt>Reportante</dt><dd>{{ $inc->reportante->nombres }}@if ($inc->reportante->departamento) <span class="dim">({{ $inc->reportante->departamento }})</span>@endif</dd>
                <dt>Técnico</dt><dd>@if ($inc->tecnico){{ $inc->tecnico->nombres }}@else<span class="dim">Sin asignar</span>@endif</dd>
                <dt>Registrada</dt><dd>{{ $inc->created_at->format('d/m/Y H:i') }}</dd>
                <dt>Actualizada</dt><dd>{{ $inc->updated_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>

        @if ($inc->informe_tecnico)
            <div class="sec"><h4><i data-lucide="file-check"></i>Informe técnico</h4>
                <div class="act-box g"><p style="font-size:13.5px;white-space:pre-line">{{ $inc->informe_tecnico }}</p>
                    @if ($fotoSolucion)<img class="evidence" style="margin-top:10px" src="{{ route('evidencias.ver', $fotoSolucion) }}" alt="Evidencia de la solución">@endif
                </div></div>
        @endif

        {{-- ===== Acciones del operador / administrador ===== --}}
        @if ($gestiona && $inc->estado->antesDeAsignar())
            <div class="sec"><h4><i data-lucide="clipboard-check"></i>Evaluar clasificación IA</h4>
                <div class="act-box a">
                    <form method="POST" action="{{ route('incidencias.reclasificar', $inc) }}">@csrf
                        <div class="row row-2" style="margin-bottom:10px">
                            <div class="field" style="margin:0;grid-column:1/-1"><label for="m-cat">Categoría</label>
                                <select id="m-cat" name="categoria_id" data-cat="m-sub">
                                    @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected($c->id === $inc->categoria_id)>{{ $c->nombre }}</option>@endforeach
                                </select></div>
                            <div class="field" style="margin:0"><label for="m-sub">Subcategoría</label>
                                <select id="m-sub" name="subcategoria_id" data-elegida="{{ $inc->subcategoria_id }}"><option value="">—</option></select></div>
                            <div class="field" style="margin:0"><label for="m-prio">Prioridad</label>
                                <select id="m-prio" name="prioridad">
                                    @foreach ($prioridades as $p)<option value="{{ $p->value }}" @selected($p === $inc->prioridad)>{{ $p->value }}</option>@endforeach
                                </select></div>
                        </div>
                        @foreach (['categoria_id', 'subcategoria_id', 'prioridad'] as $campo)
                            @error($campo)<div class="field-err" role="alert">{{ $message }}</div>@enderror
                        @endforeach
                        <button class="btn btn-amber btn-sm" type="submit"><i data-lucide="pencil"></i>Guardar modificación manual</button>
                    </form>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <form method="POST" action="{{ route('incidencias.validar', $inc) }}">@csrf
                            <button class="btn btn-primary btn-sm" type="submit"><i data-lucide="check-check"></i>Validar clasificación IA</button></form>
                        @if ($inc->estado === \App\Enums\EstadoIncidencia::Registrada)
                            <form method="POST" action="{{ route('incidencias.evaluar', $inc) }}">@csrf
                                <button class="btn btn-sm" type="submit"><i data-lucide="play"></i>Iniciar evaluación</button></form>
                        @endif
                    </div>
                </div></div>

            <div class="sec"><h4><i data-lucide="user-check"></i>Asignar técnico</h4>
                <div class="act-box">
                    @if ($tecnicos->isNotEmpty())
                        <form method="POST" action="{{ route('incidencias.asignar', $inc) }}">@csrf
                            <div class="field"><label for="m-tec">Técnicos disponibles</label>
                                <select id="m-tec" name="tecnico_id" required>
                                    @foreach ($tecnicos as $t)<option value="{{ $t->id }}">{{ $t->nombres }}@if ($t->departamento) — {{ $t->departamento }}@endif · {{ $t->activos }} ticket(s) activo(s)</option>@endforeach
                                </select>
                                @error('tecnico_id')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                            <button class="btn btn-cyan" type="submit"><i data-lucide="user-plus"></i>Asignar y pasar a «Asignada»</button>
                        </form>
                    @else
                        <div class="muted">No hay técnicos activos disponibles. Apruébalos o invítalos en «Usuarios y roles».</div>
                    @endif
                </div></div>
        @endif

        @if ($gestiona && $inc->estado === \App\Enums\EstadoIncidencia::Resuelta)
            <div class="sec"><h4><i data-lucide="lock"></i>Cierre definitivo</h4>
                <div class="act-box g">
                    <p class="muted" style="margin-bottom:10px;font-size:13px">Revisa el informe del técnico y confirma el cierre del ticket.</p>
                    <form method="POST" action="{{ route('incidencias.cerrar', $inc) }}" data-confirmar="¿Cerrar definitivamente este ticket?">@csrf
                        <button class="btn btn-primary" type="submit"><i data-lucide="check-circle"></i>Cerrar ticket (Resuelta → Cerrada)</button></form>
                </div></div>
        @endif

        {{-- ===== Acciones del técnico ===== --}}
        @if ($esMiTarea && $inc->estado === \App\Enums\EstadoIncidencia::Asignada)
            <div class="sec"><h4><i data-lucide="wrench"></i>Acción rápida</h4>
                <div class="act-box">
                    <form method="POST" action="{{ route('incidencias.iniciar', $inc) }}">@csrf
                        <button class="btn btn-cyan" type="submit"><i data-lucide="play"></i>Iniciar atención (Asignada → En atención)</button></form>
                </div></div>
        @endif

        @if ($esMiTarea && $inc->estado === \App\Enums\EstadoIncidencia::EnAtencion)
            <div class="sec"><h4><i data-lucide="file-check"></i>Informe técnico de reparación</h4>
                <div class="act-box g">
                    <form method="POST" action="{{ route('incidencias.resolver', $inc) }}" enctype="multipart/form-data">@csrf
                        <div class="field"><label for="m-informe">Informe *</label>
                            <textarea id="m-informe" name="informe" rows="4" maxlength="4000" required placeholder="Describe el trabajo realizado, materiales usados y pruebas de funcionamiento…">{{ old('informe') }}</textarea>
                            @error('informe')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                        <div class="field"><label for="m-foto">Foto de la solución (opcional)</label>
                            <input type="file" id="m-foto" name="foto" accept="image/jpeg,image/png,image/webp">
                            @error('foto')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                        <button class="btn btn-primary" type="submit"><i data-lucide="check"></i>Marcar como Resuelta</button>
                    </form>
                </div></div>
        @endif

        <div class="sec"><h4><i data-lucide="history"></i>Historial</h4>
            <div class="hist">
                @foreach ($inc->historial->reverse() as $h)
                    <div><span class="when">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                        <span><b>{{ $h->estado->value }}</b> · {{ $h->nota }} <span class="dim">— {{ $h->usuario?->nombres ?? 'Sistema' }}</span></span></div>
                @endforeach
            </div>
        </div>
    </div>

    <div>
        <div class="sec" style="margin-top:0"><h4><i data-lucide="camera"></i>Evidencia</h4>
            @if ($fotoReporte)
                <img class="evidence" src="{{ route('evidencias.ver', $fotoReporte) }}" alt="Evidencia de la incidencia">
            @else
                <div class="card empty" style="padding:22px"><i data-lucide="image-off"></i><div>Sin fotografía adjunta</div></div>
            @endif
        </div>

        @if ($ia)
            <div class="sec"><h4><i data-lucide="sparkles" style="color:var(--p)"></i>Clasificación IA</h4>
                <div class="card ai-card" style="padding:14px">
                    <div class="conf-wrap" style="margin:0 0 10px">
                        <div class="conf-ring" style="--v:{{ (int) $ia->confianza }};width:64px;height:64px"><span style="font-size:15px">{{ (int) $ia->confianza }}%</span></div>
                        <div>
                            <div style="font-weight:700">{{ $ia->categoria->nombre }}</div>
                            <div class="muted" style="font-size:12px">{{ $ia->subcategoria?->nombre ?? '—' }}</div>
                            <div style="margin-top:5px;display:flex;gap:5px;flex-wrap:wrap">
                                <span class="badge {{ $ia->prioridad->claseInsignia() }}">{{ $ia->prioridad->value }}</span>
                                @if ($ia->validada)<span class="badge e-Resuelta"><i data-lucide="check"></i>Validada</span>
                                @elseif ($ia->modificada)<span class="badge e-En-evaluación">Modificada</span>
                                @else<span class="badge b-ai">Sin validar</span>@endif
                            </div>
                        </div>
                    </div>
                    @if ($ia->lista('palabras_clave') || $ia->lista('objetos'))
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                            @foreach ($ia->lista('palabras_clave') as $p)<span class="chip chip-p"><i data-lucide="text-cursor"></i>{{ $p }}</span>@endforeach
                            @foreach ($ia->lista('objetos') as $o)<span class="chip chip-p"><i data-lucide="scan-eye"></i>{{ $o }}</span>@endforeach
                        </div>
                    @endif
                    <div class="expl">
                        @foreach (explode("\n", $ia->explicacion) as $linea)<div><i data-lucide="chevron-right"></i><span>{{ $linea }}</span></div>@endforeach
                    </div>
                </div></div>
        @endif
    </div>
</div>
@endsection
