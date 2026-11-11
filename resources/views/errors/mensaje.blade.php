@extends('layouts.base')

@section('titulo', $titulo)

@section('contenido')
<div class="guest-wrap">
    <div class="card guest-card" style="text-align:center">
        <div class="brand" style="justify-content:center;padding-bottom:14px"><div class="brand-logo"><i data-lucide="cpu"></i></div><div style="text-align:left"><b>SmartCampus</b><small>AI PLATFORM</small></div></div>
        <h1 style="font-size:22px;margin-bottom:8px">{{ $titulo }}</h1>
        <p class="muted" style="margin-bottom:18px">{{ $detalle }}</p>
        <a class="btn btn-primary" href="{{ url('/') }}"><i data-lucide="home"></i>Volver al inicio</a>
    </div>
</div>
@endsection
