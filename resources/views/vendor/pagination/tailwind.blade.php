@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[12.5px] text-ink-muted">
            Showing
            <span class="font-medium tabular-nums text-ink-soft">{{ $paginator->firstItem() }}</span>
            to
            <span class="font-medium tabular-nums text-ink-soft">{{ $paginator->lastItem() }}</span>
            of
            <span class="font-medium tabular-nums text-ink-soft">{{ $paginator->total() }}</span>
            {{ Str::plural('task', $paginator->total()) }}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-line bg-surface px-2 text-[13px] text-ink-faint" aria-disabled="true">
                    &lsaquo;
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-line bg-surface px-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-hover hover:text-ink">
                    &lsaquo;
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-8 min-w-8 items-center justify-center px-1 text-[13px] text-ink-faint">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="inline-flex h-8 min-w-8 items-center justify-center rounded-md bg-accent px-2 text-[13px] font-medium tabular-nums text-white">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-[13px] font-medium tabular-nums text-ink-soft transition-colors hover:bg-surface-hover hover:text-ink">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-line bg-surface px-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-hover hover:text-ink">
                    &rsaquo;
                </a>
            @else
                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-line bg-surface px-2 text-[13px] text-ink-faint" aria-disabled="true">
                    &rsaquo;
                </span>
            @endif
        </div>
    </nav>
@endif