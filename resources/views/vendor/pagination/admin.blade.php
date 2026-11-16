@php
    $paginator = $paginator ?? $__env->getLastResolvedData()['paginator'] ?? null;
    if (!$paginator) {
        return;
    }
    $count = $paginator->count();
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $perPage = $paginator->perPage();
    $total = $paginator->total();
    $first = $paginator->firstItem();
    $last = $paginator->lastItem();
@endphp

@if ($lastPage > 1)
<div class="nl-pagination">
    <div class="nl-pagination-info">
        Showing {{ $first }} to {{ $last }} of {{ $total }}
    </div>

    <nav class="nl-pagination-links">
        {{-- Previous --}}
        @if ($currentPage > 1)
            <a href="{{ $paginator->url($currentPage - 1) }}" class="nl-pagination-btn nl-pagination-prev">Previous</a>
        @else
            <span class="nl-pagination-btn nl-pagination-disabled">Previous</span>
        @endif

        {{-- Pages --}}
        @foreach ($paginator->getUrlRange(1, $lastPage) as $page => $url)
            @if ($page == $currentPage)
                <span class="nl-pagination-btn nl-pagination-current">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="nl-pagination-btn">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next --}}
        @if ($currentPage < $lastPage)
            <a href="{{ $paginator->url($currentPage + 1) }}" class="nl-pagination-btn nl-pagination-next">Next</a>
        @else
            <span class="nl-pagination-btn nl-pagination-disabled">Next</span>
        @endif
    </nav>
</div>
@endif

<style>
.nl-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 0;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem;
    color: var(--muted);
    border-top: 1px solid var(--border);
}

.nl-pagination-info {
    font-size: 0.8rem;
    color: var(--muted);
}

.nl-pagination-links {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.nl-pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--card);
    color: var(--text);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.nl-pagination-btn:hover:not(.nl-pagination-disabled) {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(2, 132, 199, 0.06);
}

.nl-pagination-current {
    border-color: var(--accent);
    background: var(--accent);
    color: #ffffff;
}

.nl-pagination-disabled {
    border-color: var(--border2);
    color: var(--muted);
    background: var(--card2);
    cursor: not-allowed;
}

.nl-pagination-prev, .nl-pagination-next {
    min-width: auto;
    padding: 0 0.75rem;
}
</style>
