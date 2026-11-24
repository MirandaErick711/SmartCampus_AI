{{--
    Campo de formulario con su error.
    Parámetros: $id, $nombre, $etiqueta, $tipo (text), $bolsa (default), $ph, $auto, $requerido (true), $valor
--}}
@php
    $tipo = $tipo ?? 'text';
    $bolsa = $bolsa ?? 'default';
    $requerido = $requerido ?? true;
    $valor = $tipo === 'password' ? '' : ($valor ?? old($nombre));
@endphp
<div class="field">
    <label for="{{ $id }}">{{ $etiqueta }}{{ $requerido ? ' *' : '' }}</label>
    <input id="{{ $id }}" name="{{ $nombre }}" type="{{ $tipo }}" value="{{ $valor }}"
           @if (! empty($auto)) autocomplete="{{ $auto }}" @endif
           @if (! empty($ph)) placeholder="{{ $ph }}" @endif
           @if ($requerido) required @endif>
    @error($nombre, $bolsa)
        <div class="field-err" role="alert">{{ $message }}</div>
    @enderror
</div>
