{!! $titulo !!}

{!! $saludo !!}

@foreach ($parrafos as $parrafo)
{!! $parrafo !!}

@endforeach
@if ($botonTexto && $botonUrl)
{!! $botonTexto !!}: {!! $botonUrl !!}

@endif
{!! $pie !!}
