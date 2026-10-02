@php
    $tone = match ($heading) {
        'Late Tasks' => 'text-late',
        default => 'text-ink-faint',
    };
@endphp

<section class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <div class="flex items-center gap-2">
            <x-icon :name="$icon" class="size-4 shrink-0 {{ $tone }}" />
            <h2 class="text-[13px] font-semibold text-ink">{{ $heading }}</h2>
        </div>
        @if ($tasks->isNotEmpty())
            <span class="rounded-full bg-surface-sunken px-2 py-0.5 text-[11px] font-medium tabular-nums text-ink-muted">
                {{ $tasks->count() }}
            </span>
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
                                    {{ $task->deadline->format('d M Y') }}
                                    <span class="text-ink-faint">{{ $task->deadline->format('H:i') }}</span>
                                </span>
                            </p>
                        </div>

                        <x-status-badge :status="$task->status" size="sm" class="mt-0.5 shrink-0" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>