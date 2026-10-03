@php
    $tone = match ($heading) {
        'Tugas Terlambat' => 'text-late',
        default => 'text-ink-faint',
    };

    /*
     * Daftar dashboard hanya menampilkan cuplikan (5 item). `$total` adalah
     * jumlah SEBENARNYA, sedangkan `$tasks->count()` dibatasi 5. Lencana
     * memakai `$total`, dan tautan "Lihat semua" hanya muncul kalau memang
     * ada yang belum ditampilkan - tanpa itu user tidak pernah tahu task
     * terlambatnya masih ada di halaman daftar.
     */
    $shown = $tasks->count();
    $total = $total ?? $shown;
    $hasMore = $total > $shown;
@endphp

<section class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <div class="flex min-w-0 items-center gap-2">
            <x-icon :name="$icon" class="size-4 shrink-0 {{ $tone }}" />
            <h2 class="truncate text-[13px] font-semibold text-ink">{{ $heading }}</h2>

            @if ($total > 0)
                <span
                    class="shrink-0 rounded-full bg-surface-sunken px-2 py-0.5 text-[11px] font-medium tabular-nums text-ink-muted"
                    title="{{ $total }} {{ Str::plural('tugas', $total) }}"
                >
                    {{ $total }}
                </span>
            @endif
        </div>

        @if ($hasMore && ! empty($filterUrl))
            <a
                href="{{ $filterUrl }}"
                class="shrink-0 text-[12px] font-medium text-ink-soft transition-colors hover:text-ink hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1"
            >
                Lihat semua
                <span class="sr-only"> {{ $heading }} ({{ $total }})</span>
            </a>
        @endif
    </header>

    @if ($tasks->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center px-4 py-10 text-center">
            <x-icon :name="$icon" class="size-5 text-ink-faint/60" />
            <p class="mt-2 text-[13px] text-ink-muted">{{ $emptyMessage }}</p>
        </div>
    @else
        <ul class="flex-1 divide-y divide-line">
            @foreach ($tasks as $task)
                <li class="px-4 py-3 transition-colors hover:bg-surface-hover/60">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-medium text-ink">{{ $task->task }}</p>

                            <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11.5px] text-ink-muted">
                                @if ($isAdmin && $task->trainee)
                                    <span class="font-medium text-ink-soft">{{ $task->trainee->name }}</span>
                                    <span class="text-ink-faint" aria-hidden="true">&middot;</span>
                                @endif
                                <span class="tabular-nums">
                                    {{ $task->deadline->translatedFormat('d M Y') }}
                                    <span class="text-ink-faint">{{ $task->deadline->format('H:i') }}</span>
                                </span>
                            </p>
                        </div>

                        <x-status-badge :status="$task->status" size="sm" class="mt-0.5 shrink-0" />
                    </div>
                </li>
            @endforeach
        </ul>

        {{--
            Disediakan supaya task yang tidak masuk cuplikan 5 item tetap punya
            jalan keluar yang jelas, termasuk task yang sudah terlambat.
        --}}
        @if ($hasMore && ! empty($filterUrl))
            <div class="border-t border-line px-4 py-2.5">
                <a
                    href="{{ $filterUrl }}"
                    class="flex items-center justify-center gap-1.5 text-[12.5px] font-medium text-ink-soft transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1"
                >
                    {{ $total - $shown }} {{ Str::plural('tugas', $total - $shown) }} lainnya
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>
        @endif
    @endif
</section>