@extends('layouts.app')

@section('titulo', 'Reportar incidencia')

@section('vista')
<div class="page-h">
    <div>
        <h1>Reportar <span class="txt-g">incidencia</span></h1>
        <p>Describe el problema, indica dónde ocurre y, si puedes, adjunta una foto. La IA sugiere la clasificación.</p>
    </div>
</div>

<script type="application/json" id="datos-ubicaciones">{!! json_encode($ubicaciones, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="datos-categorias">{!! json_encode($categoriasJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

<form method="POST" action="{{ route('incidencias.store') }}" enctype="multipart/form-data" id="form-reporte" class="grid g-12"
      data-analizar="{{ route('incidencias.analizar') }}" data-viejo-ubicacion="{{ old('ubicacion_id') }}">
    @csrf

    <div class="card">
        <div class="card-h"><h3><i data-lucide="file-text"></i>Datos de la incidencia</h3></div>

        <div class="field">
            <label for="f-titulo">Título *</label>
            <input id="f-titulo" name="titulo" maxlength="180" required value="{{ old('titulo') }}" placeholder="Ej.: Luminaria parpadea en el aula">
            @error('titulo')<div class="field-err" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="f-desc">Descripción *</label>
            <textarea id="f-desc" name="descripcion" rows="5" maxlength="4000" required placeholder="¿Qué ocurre, desde cuándo y qué tan grave es?">{{ old('descripcion') }}</textarea>
            @error('descripcion')<div class="field-err" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="row row-2">
            <div class="field"><label for="f-campus">Campus</label><select id="f-campus"><option value="">Seleccionar…</option></select></div>
            <div class="field"><label for="f-pab">Pabellón</label><select id="f-pab" disabled><option value="">Seleccionar…</option></select></div>
            <div class="field"><label for="f-piso">Piso</label><select id="f-piso" disabled><option value="">Seleccionar…</option></select></div>
            <div class="field"><label for="f-aula">Aula / laboratorio *</label><select id="f-aula" name="ubicacion_id" required disabled><option value="">Seleccionar…</option></select></div>
        </div>
        @error('ubicacion_id')<div class="field-err" role="alert" style="margin-top:-8px;margin-bottom:10px">{{ $message }}</div>@enderror

        <div class="field">
            <label for="f-area">Área afectada *</label>
            <select id="f-area" name="area_id" required>
                <option value="">Seleccionar…</option>
                @foreach ($areas as $a)<option value="{{ $a->id }}" @selected((int) old('area_id') === $a->id)>{{ $a->nombre }}</option>@endforeach
            </select>
            @error('area_id')<div class="field-err" role="alert">{{ $message }}</div>@enderror
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-h"><h3><i data-lucide="camera"></i>Evidencia fotográfica</h3><span class="chip">Opcional · mejora la confianza IA</span></div>
            <input type="file" id="f-foto" name="foto" accept="image/jpeg,image/png,image/webp" hidden>
            <div id="foto-caja">
                <div class="dropzone" id="dz" tabindex="0" role="button" aria-label="Elegir una fotografía">
                    <i data-lucide="upload-cloud"></i>
                    <div><b>Arrastra una imagen</b> o haz clic para seleccionar</div>
                    <div class="dim" style="font-size:11px;margin-top:4px">JPG, PNG o WEBP · máx. 8 MB</div>
                </div>
            </div>
            @error('foto')<div class="field-err" role="alert" style="margin-top:8px">{{ $message }}</div>@enderror
        </div>

        <div class="card ai-card">
            <div class="card-h"><h3><i data-lucide="sparkles"></i>Análisis multimodal <span class="badge b-ai">IA</span></h3></div>
            <button type="button" class="btn btn-ai btn-block" id="btn-analizar"><i data-lucide="scan-search"></i>Analizar con SmartCampus AI</button>
            <div id="ia-resultado" style="margin-top:14px" aria-live="polite">
                <div class="muted" style="font-size:12.5px;text-align:center;padding:6px 0">Completa el título y la descripción y pulsa el botón para clasificar automáticamente. Si no lo haces, la IA clasifica al registrar.</div>
            </div>
            <div class="row row-2" style="margin-top:14px">
                <div class="field" style="grid-column:1/-1;margin-bottom:10px"><label for="f-cat">Categoría</label>
                    <select id="f-cat" name="categoria_id" data-cat="f-sub">
                        <option value="">— que decida la IA —</option>
                        @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected((int) old('categoria_id') === $c->id)>{{ $c->nombre }}</option>@endforeach
                    </select></div>
                <div class="field" style="margin:0"><label for="f-sub">Subcategoría</label>
                    <select id="f-sub" name="subcategoria_id" data-elegida="{{ old('subcategoria_id') }}"><option value="">—</option></select></div>
                <div class="field" style="margin:0"><label for="f-prio">Prioridad</label>
                    <select id="f-prio" name="prioridad"><option value="">— que decida la IA —</option>
                        @foreach ($prioridades as $p)<option value="{{ $p->value }}" @selected(old('prioridad') === $p->value)>{{ $p->value }}</option>@endforeach
                    </select></div>
            </div>
            @error('categoria_id')<div class="field-err" role="alert">{{ $message }}</div>@enderror
            @error('subcategoria_id')<div class="field-err" role="alert">{{ $message }}</div>@enderror
            @error('prioridad')<div class="field-err" role="alert">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="padding:13px"><i data-lucide="send"></i>Registrar incidencia</button>
    </div>
</form>
@endsection
