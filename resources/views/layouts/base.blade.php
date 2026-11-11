<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#000000">
<title>@yield('titulo', 'SmartCampus AI') · SmartCampus AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@yield('contenido')

@include('partials.avisos')

<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
