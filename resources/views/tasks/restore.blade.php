@extends('layouts.app')

@section('title', 'Pulihkan Tugas')
@section('heading', 'Pulihkan Tugas')
@section('subheading', 'Ganti tanggal ke masa depan, lalu kembalikan tugas ini ke daftar aktif')

@section('content')
<div class="space-y-3">
    <x-button variant="secondary" :href="route('admin.tasks.trash')">
        <x-icon name="arrow-right" class="size-4 rotate-180" />
        Kembali ke Sampah
    </x-button>

    <form
        method="POST"
        action="{{ route('admin.tasks.restore', ['task' => $task->id]) }}"
        class="mx-auto w-full max-w-2xl overflow-hidden rounded-xl border border-line bg-surface shadow-xs"
    >
        @csrf

        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div class="min-w-0">
                <h2 class="truncate text-base font-semibold tracking-tight text-ink">{{ $task->task }}</h2>
                <p class="mt-1 font-mono text-[12px] text-ink-faint">{{ $task->id }}</p>
                @if ($task->desc)
                    <p class="mt-2 text-[13px] text-ink-muted">{{ $task->desc }}</p>
                @endif
            </div>

            {{-- Badge statis: `late` adalah status milik sistem. --}}
            <x-status-badge :status="$task->status" />
        </div>

        <div class="space-y-5 px-6 py-5">
            <div class="grid gap-4 rounded-lg border border-line bg-surface-sunken px-4 py-3 sm:grid-cols-3">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">Trainee</p>
                    <p class="mt-0.5 truncate text-[13px] text-ink-soft">{{ $task->trainee?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">Deadline lama</p>
                    <p class="mt-0.5 text-[13px] text-ink-soft">
                        {{ $task->deadline->translatedFormat('d M Y') }}
                        <span class="text-ink-faint">{{ $task->deadline->format('H:i') }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-ink-faint">Dihapus</p>
                    <p class="mt-0.5 text-[13px] text-ink-soft">
                        {{ $task->deleted_at?->translatedFormat('d M Y') ?? '—' }}
                        <span class="text-ink-faint">{{ $task->deleted_at?->format('H:i') }}</span>
                    </p>
                </div>
            </div>

            {{--
                Tanggal WAJIB diganti. Nilai lama sudah lewat, dan memulihkan
                tanpa tanggal baru akan membuat task langsung terlambat lagi.
                Server memvalidasi ulang (RestoreTaskRequest) - nilai awal di
                sini hanya permeateks, bukan aturan.
            --}}
            <div class="rounded-lg border border-line bg-surface-sunken px-4 py-3">
                <p class="text-[13px] font-medium text-ink-soft">Tanggal baru</p>
                <p class="mt-0.5 text-xs text-ink-faint">
                    Harus sudah lewat dari waktu sekarang. Status
                    &quot;{{ $task->status->label() }}&quot; otomatis dikembalikan menjadi
                    &quot;Progress&quot; setelah dipulihkan bila statusnya &quot;Terlambat&quot;.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="start_date" class="block text-[13px] font-medium text-ink-soft">
                        Tanggal Mulai
                    </label>
                    <input
                        type="datetime-local"
                        id="start_date"
                        name="start_date"
                        value="{{ old('start_date', $suggestedStart->format('Y-m-d\TH:i')) }}"
                        min="{{ now()->format('Y-m-d\TH:i') }}"
                        required
                        class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                    >
                    <x-field-error name="start_date" />
                </div>

                <div>
                    <label for="deadline" class="block text-[13px] font-medium text-ink-soft">
                        Tanggal Deadline
                    </label>
                    <input
                        type="datetime-local"
                        id="deadline"
                        name="deadline"
                        value="{{ old('deadline', $suggestedDeadline->format('Y-m-d\TH:i')) }}"
                        min="{{ now()->format('Y-m-d\TH:i') }}"
                        required
                        class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                    >
                    <x-field-error name="deadline" />
                </div>
            </div>

            <p class="text-xs text-ink-faint">
                Setelah dipulihkan, tugas ini kembali muncul di daftar tugas dan dihitung
                pada dashboard. Bila tanggal deadline diisi kurang dari tanggal mulai,
                atau masih di masa lalu, form akan ditolak.
            </p>
        </div>

        <div class="flex justify-end gap-2.5 border-t border-line bg-surface-sunken px-6 py-4">
            <x-button variant="secondary" :href="route('admin.tasks.trash')">Batal</x-button>
            <x-button type="submit">
                <x-icon name="arrow-right" class="size-4 rotate-180" />
                Pulihkan Tugas
            </x-button>
        </div>
    </form>
</div>
@endsection