@if ($paginator->hasPages())
    <nav class="pagination-bar" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="pagination-btn disabled">Anterior</span>
        @else
            <a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        <span class="pagination-info">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
        @else
            <span class="pagination-btn disabled">Siguiente</span>
        @endif
    </nav>
@endif
