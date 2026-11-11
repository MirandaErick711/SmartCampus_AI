@extends('layouts.app')

@section('titulo', 'Ubicaciones')

@php
    $abrir = collect(['campus', 'pabellon', 'ubicacion'])->first(fn ($b) => $errors->getBag($b)->any()) ?? '';
    $todosPabellones = $campus->flatMap(fn ($c) => $c->pabellones->map(fn ($p) => [$p, $c]));
@endphp

@section('vista')
<div id="estado-inicial" data-modal="{{ $abrir }}" hidden></div>

<div class="page-h">
    <div>
        <h1>Ubicaciones y <span class="txt-p">catálogos</span></h1>
        <p>Agrega campus, pabellones, aulas y laboratorios. Aparecen de inmediato en el formulario de reportes.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn" data-abrir="campus"><i data-lucide="building-2"></i>Campus</button>
        <button type="button" class="btn" data-abrir="pabellon"><i data-lucide="building"></i>Pabellón</button>
        <button type="button" class="btn btn-primary" data-abrir="ubicacion"><i data-lucide="door-open"></i>Aula o laboratorio</button>
    </div>
</div>

@forelse ($campus as $c)
    <div class="card" style="margin-bottom:14px">
        <div class="card-h">
            <h3><i data-lucide="building-2"></i>{{ $c->nombre }}</h3>
            @if ($c->pabellones->isEmpty())
                <form method="POST" action="{{ route('ubicaciones.campus.destroy', $c) }}" data-confirmar="¿Eliminar el campus {{ $c->nombre }}?">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="Eliminar campus"><i data-lucide="trash-2"></i></button></form>
            @endif
        </div>
        @forelse ($c->pabellones as $p)
            <details class="tree card" style="margin-bottom:8px">
                <summary>
                    <i data-lucide="building"></i>
                    <span>{{ $p->nombre }} <span class="muted" style="font-weight:400;font-size:12px">· {{ $p->descripcion ?: 'Sin descripción' }} · {{ $p->pisos }} piso(s) · {{ $p->ubicaciones->count() }} ambiente(s)</span></span>
                </summary>
                <div class="inner">
                    @foreach ($p->ubicaciones->groupBy('piso') as $piso => $lugares)
                        <div class="floor"><b>Piso {{ $piso }}</b>
                            <div class="chips">
                                @foreach ($lugares as $l)
                                    <span class="chip">
                                        <i data-lucide="{{ $l->tipo === 'laboratorio' ? 'flask-conical' : 'door-open' }}"></i>{{ $l->nombre }}
                                        <form method="POST" action="{{ route('ubicaciones.ubicacion.destroy', $l) }}" style="display:inline;margin:0" data-confirmar="¿Eliminar {{ $l->nombre }} de {{ $p->nombre }}?">@csrf @method('DELETE')
                                            <button type="submit" class="dim" style="background:none;border:0;cursor:pointer;padding:0 0 0 4px;color:inherit" aria-label="Eliminar {{ $l->nombre }}">×</button></form>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @if ($p->ubicaciones->isEmpty())
                        <div class="muted" style="font-size:12.5px;margin-bottom:8px">Aún no hay ambientes en este pabellón.</div>
                        <form method="POST" action="{{ route('ubicaciones.pabellon.destroy', $p) }}" data-confirmar="¿Eliminar el pabellón {{ $p->nombre }}?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit"><i data-lucide="trash-2"></i>Eliminar pabellón</button></form>
                    @endif
                </div>
            </details>
        @empty
            <div class="muted" style="font-size:13px">Este campus aún no tiene pabellones.</div>
        @endforelse
    </div>
@empty
    <div class="card empty"><i data-lucide="map-pin-off"></i><div>Aún no hay ubicaciones. Empieza agregando un campus.</div></div>
@endforelse

<div class="card" style="margin-top:6px">
    <div class="card-h"><h3><i data-lucide="sparkles"></i>Categorías y palabras clave de la IA</h3><span class="chip">Solo lectura</span></div>
    <p class="muted" style="font-size:12.5px;margin-bottom:12px">Así clasifica el motor de IA: busca estas palabras en el título y la descripción del reporte.</p>
    @foreach ($palabras as $categoria => $lista)
        <div class="floor"><b style="min-width:110px">{{ $categoria }}</b>
            <div class="chips">@foreach ($lista as $w)<span class="chip chip-p">{{ $w }}</span>@endforeach</div></div>
    @endforeach
</div>

{{-- ================= CUADROS ================= --}}
<div class="modal-back" id="modal-campus" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-campus">
        <div class="modal-h"><div><h3 id="t-campus">Nuevo campus</h3></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button></div>
        <form method="POST" action="{{ route('ubicaciones.campus') }}" autocomplete="off">@csrf
            <div class="modal-b">
                @include('partials.campo', ['id' => 'cp-nombre', 'nombre' => 'nombre', 'etiqueta' => 'Nombre del campus', 'bolsa' => 'campus', 'ph' => 'Campus Sur'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="plus"></i>Agregar campus</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="modal-pabellon" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-pabellon">
        <div class="modal-h"><div><h3 id="t-pabellon">Nuevo pabellón</h3></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button></div>
        <form method="POST" action="{{ route('ubicaciones.pabellon') }}" autocomplete="off">@csrf
            <div class="modal-b">
                <div class="field"><label for="pb-campus">Campus *</label>
                    <select id="pb-campus" name="campus_id" required><option value="">Seleccionar…</option>
                        @foreach ($campus as $c)<option value="{{ $c->id }}" @selected((int) old('campus_id') === $c->id)>{{ $c->nombre }}</option>@endforeach
                    </select>
                    @error('campus_id', 'pabellon')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                @include('partials.campo', ['id' => 'pb-nombre', 'nombre' => 'nombre', 'etiqueta' => 'Nombre', 'bolsa' => 'pabellon', 'ph' => 'Pabellón F'])
                @include('partials.campo', ['id' => 'pb-desc', 'nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'bolsa' => 'pabellon', 'requerido' => false])
                @include('partials.campo', ['id' => 'pb-pisos', 'nombre' => 'pisos', 'etiqueta' => 'Cantidad de pisos', 'tipo' => 'number', 'bolsa' => 'pabellon'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="plus"></i>Agregar pabellón</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="modal-ubicacion" hidden>
    <div class="modal sm" role="dialog" aria-modal="true" aria-labelledby="t-ubicacion">
        <div class="modal-h"><div><h3 id="t-ubicacion">Nueva aula o laboratorio</h3></div>
            <button type="button" class="btn btn-icon" data-cerrar aria-label="Cerrar"><i data-lucide="x"></i></button></div>
        <form method="POST" action="{{ route('ubicaciones.ubicacion') }}" autocomplete="off">@csrf
            <div class="modal-b">
                <div class="field"><label for="ub-pab">Pabellón *</label>
                    <select id="ub-pab" name="pabellon_id" required><option value="">Seleccionar…</option>
                        @foreach ($todosPabellones as [$p, $c])<option value="{{ $p->id }}" @selected((int) old('pabellon_id') === $p->id)>{{ $c->nombre }} › {{ $p->nombre }} ({{ $p->pisos }} pisos)</option>@endforeach
                    </select>
                    @error('pabellon_id', 'ubicacion')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                <div class="row row-2">
                    @include('partials.campo', ['id' => 'ub-piso', 'nombre' => 'piso', 'etiqueta' => 'Piso', 'tipo' => 'number', 'bolsa' => 'ubicacion'])
                    <div class="field"><label for="ub-tipo">Tipo *</label>
                        <select id="ub-tipo" name="tipo" required>
                            @foreach (\App\Models\Ubicacion::TIPOS as $valor => $texto)<option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $texto }}</option>@endforeach
                        </select>
                        @error('tipo', 'ubicacion')<div class="field-err" role="alert">{{ $message }}</div>@enderror</div>
                </div>
                @include('partials.campo', ['id' => 'ub-nombre', 'nombre' => 'nombre', 'etiqueta' => 'Nombre', 'bolsa' => 'ubicacion', 'ph' => 'Aula 104'])
                <button class="btn btn-primary btn-block" type="submit"><i data-lucide="plus"></i>Agregar ambiente</button>
            </div>
        </form>
    </div>
</div>
@endsection
