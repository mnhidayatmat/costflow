@if ($paginator->hasPages())
  <nav class="cf-pager" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="disabled"><span>‹ Prev</span></span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="disabled"><span>{{ $element }}</span></span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="active" aria-current="page"><span>{{ $page }}</span></span>
          @else
            <a href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
    @else
      <span class="disabled"><span>Next ›</span></span>
    @endif
  </nav>
@endif
