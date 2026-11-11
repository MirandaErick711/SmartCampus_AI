{{-- Mensajes del servidor (éxito, aviso, error) como "toasts" --}}
<div class="toasts" id="toasts" aria-live="polite">
    @foreach (['exito' => ['', 'check-circle-2'], 'aviso' => ['info', 'info'], 'error' => ['error', 'alert-triangle']] as $clave => [$clase, $icono])
        @if (session($clave))
            <div class="toast {{ $clase }}" role="status">
                <i data-lucide="{{ $icono }}"></i><span>{{ session($clave) }}</span>
            </div>
        @endif
    @endforeach
</div>
