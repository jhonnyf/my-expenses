@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginação">
        <ol class="kt-pagination">
            <li class="kt-pagination-item">
                @if ($paginator->onFirstPage())
                    <span class="kt-btn kt-btn-icon kt-btn-ghost opacity-50 pointer-events-none" aria-disabled="true" aria-label="Página anterior">
                        <i class="ki-filled ki-left"></i>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="kt-btn kt-btn-icon kt-btn-ghost" aria-label="Página anterior">
                        <i class="ki-filled ki-left"></i>
                    </a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="kt-pagination-ellipsis" aria-hidden="true">
                        <i class="ki-filled ki-dots-horizontal text-muted-foreground"></i>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="kt-pagination-item">
                            @if ($page == $paginator->currentPage())
                                <span class="kt-btn kt-btn-icon kt-btn-ghost active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="kt-btn kt-btn-icon kt-btn-ghost" aria-label="Ir para a página {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li class="kt-pagination-item">
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="kt-btn kt-btn-icon kt-btn-ghost" aria-label="Próxima página">
                        <i class="ki-filled ki-right"></i>
                    </a>
                @else
                    <span class="kt-btn kt-btn-icon kt-btn-ghost opacity-50 pointer-events-none" aria-disabled="true" aria-label="Próxima página">
                        <i class="ki-filled ki-right"></i>
                    </span>
                @endif
            </li>
        </ol>
    </nav>
@endif
