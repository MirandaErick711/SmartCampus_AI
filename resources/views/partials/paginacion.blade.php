{{-- Paginación simple (anterior / siguiente). Parámetro: $p (paginador) --}}
@if ($p->hasPages())
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap">
        @if ($p->onFirstPage())
            <span class="btn btn-sm" style="opacity:.4;pointer-events:none"><i data-lucide="chevron-left"></i>Anterior</span>
        @else
            <a class="btn btn-sm" href="{{ $p->previousPageUrl() }}"><i data-lucide="chevron-left"></i>Anterior</a>
        @endif
        <span class="muted" style="font-size:12px">Página {{ $p->currentPage() }} de {{ $p->lastPage() }}</span>
        @if ($p->hasMorePages())
            <a class="btn btn-sm" href="{{ $p->nextPageUrl() }}">Siguiente<i data-lucide="chevron-right"></i></a>
        @else
            <span class="btn btn-sm" style="opacity:.4;pointer-events:none">Siguiente<i data-lucide="chevron-right"></i></span>
        @endif
    </div>
@endif
