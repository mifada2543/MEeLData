{{--
    Bentuk mobile dari satu task.

    Tabel punya 6–7 kolom dengan `min-w-[740px]` (admin 1000px). Di layar
    390px hanya 48% kolomnya yang muat, jadi sisanya harus digeser
    horizontal - tidak praktis dipakai di HP. Karena itu di bawah `md` tabel
    diganti kartu: semua kolom jadi pasangan label -> nilai sehingga tidak
    ada yang terpotong.

    Status `late` di sini tetap memakai komponen Alpine sendiri
    (`deadlineTicker`), sama seperti di tabel. Duplikasi ini aman: tiap
    instance punya timer dan flag `done`-nya sendiri, dan listener
    `live:task-late` memang tidak ada pemakainya - keduanya hanya perlu
    membaca store `live` yang sama.

    `instance="card"` pada `<x-status-select>` wajib ada: kartu ini dan baris
    tabel merender task yang sama pada satu dokumen, jadi tanpa akhiran
    tersebut keduanya menghasilkan `id` menu yang identik (lihat catatan di
    komponen itu).
--}}
@php
    $priority = $task->workPriority();
@endphp

<article
    @class([
        'border-l-2 border-b border-line px-4 py-3.5 last:border-b-0',
        'border-l-late bg-late-surface/50' => $priority === 5,
        'border-l-late/50 bg-late-surface/20' => $priority === 4,
        'border-l-transparent' => $priority < 4,
    ])
>
    <div class="flex items-start gap-3">
        <input
            type="checkbox"
            name="ids[]"
            value="{{ $task->id }}"
            @if ($isAdmin) form="bulk-delete-form" @endif
            x-model="selected"
            class="bulk-checkbox mt-0.5 size-4 shrink-0 rounded border-line-strong accent-[var(--color-accent)] focus-visible:outline-none"
            aria-label="{{ $isAdmin ? 'Pilih tugas untuk aksi massal' : 'Pilih tugas untuk diekspor' }}: {{ $task->task }}"
        >

        <div class="min-w-0 flex-1">
            {{--
                Label kelompok prioritas. Di tabel ini memakai baris penuh;
                di kartu cukup label kecil di atas nama, dan hanya muncul di
                task pertama tiap kelompok (`$previousPriority`).
            --}}
            @if ($priority !== $previousPriority)
                <p
                    @class([
                        'mb-1 text-[11px] font-semibold uppercase tracking-wide',
                        'text-late' => $priority === 4,
                        'text-ink-faint' => $priority !== 4,
                    ])
                >
                    {{ $priorityLabels[$priority] }}
                </p>
            @endif

            <p class="text-[14px] font-medium leading-snug text-ink">{{ $task->task }}</p>

            @if ($task->desc)
                <p class="mt-1 text-[12.5px] leading-relaxed text-ink-muted">{{ $task->desc }}</p>
            @endif

            @if ($isAdmin && $task->trainee)
                <p class="mt-1 text-[12px] font-medium text-ink-soft">{{ $task->trainee->name }}</p>
            @endif
        </div>
    </div>

    {{-- Meta: dua kolom label -> nilai, meniru kolom tabel tanpa terpotong. --}}
    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2.5 border-t border-line pt-3">
        <div class="min-w-0">
            <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">Mulai</dt>
            <dd class="mt-0.5 whitespace-nowrap text-[13px] tabular-nums text-ink-muted">
                {{ $task->start_date->translatedFormat('d M Y') }}
                <span class="text-[12px] text-ink-faint">{{ $task->start_date->format('H:i') }}</span>
            </dd>
        </div>

        <div class="min-w-0">
            <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">Deadline</dt>
            <dd class="mt-0.5 whitespace-nowrap text-[13px] tabular-nums">
                <span class="text-ink-soft">{{ $task->deadline->translatedFormat('d M Y') }}</span>
                <span class="text-[12px] text-ink-faint">{{ $task->deadline->format('H:i') }}</span>

                @if ($task->hasCountdown())
                    <div
                        x-data="deadlineTicker(@js($task->deadline->toIso8601String()), @js($serverNowIso), true)"
                        data-task-id="{{ $task->id }}"
                    >
                        <span
                            x-show="!done"
                            class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-medium tabular-nums text-late"
                        >
                            <x-icon name="clock" class="size-3" />
                            <span x-text="text"></span>
                        </span>

                        <span x-cloak x-show="done" class="mt-0.5 block">
                            <x-status-badge :status="\App\Enums\TaskStatus::Late" size="sm" />
                        </span>
                    </div>
                @endif
            </dd>
        </div>
    </dl>

    {{-- Footer: status di kiri, aksi di kanan. --}}
    <div class="mt-3 flex items-center justify-between gap-2">
        @if ($task->status->isSystemOwned())
            <x-status-badge
                :status="$task->status"
                size="sm"
                title="Status &quot;Terlambat&quot; diatur otomatis oleh sistem dan tidak dapat diubah."
            />
        @else
            <x-status-select :task="$task" :route-prefix="$prefix" instance="card" />
        @endif

        <div class="flex shrink-0 items-center gap-1">
            @include('tasks.partials.edit-button', ['task' => $task, 'instance' => 'card'])

            @if ($isAdmin)
                @include('tasks.partials.delete-button', ['task' => $task, 'instance' => 'card'])
            @endif
        </div>
    </div>
</article>