@props(['paginator'])
@if ($paginator->hasPages())
    <div class="pagination-wrap">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <nav class="pagination" aria-label="Pagination">
            <a class="page-link {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?: '#' }}">‹</a>
            @foreach ($paginator->getUrlRange(max(1,$paginator->currentPage()-2),min($paginator->lastPage(),$paginator->currentPage()+2)) as $page => $url)
                <a class="page-link {{ $page === $paginator->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>
            @endforeach
            <a class="page-link {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() ?: '#' }}">›</a>
        </nav>
    </div>
@endif
