@extends('layouts.app')

@php
    $total = $tasks->total();
@endphp

@section('title', 'Sampah')
@section('heading', 'Sampah')
@section('subheading', $total.' '.Str::plural('tugas', $total).' · sudah dihapus dan dapat dipulihkan')

@section('content')
{{--
    Catatan overflow (hasil ukur, bukan dugaan): halaman ini TIDAK lagi
    meluber. Tabel memakai `table-fixed` + lebar kolom eksplisit dan dibungkus
    `table-scroll` (`overflow-x-auto` + `contain: paint`, lihat app.css),
    jadi di 390/768/820/1024/1440px dokumen tidak bisa digeser ke samping -
    hanya isi tabelnya yang bergeser di dalam kartu. Dulu tabel bergeser ke
    kanan karena lebar kolom tidak dipatuhi (`table-layout: auto` mengabaikan
    `w-44`), bukan karena wrapper.
--}}
<div class="space-y-3">
    {{--
        Halaman ini menampilkan task yang sudah soft-delete. Dua sumber:
         * 1. dihapus manual oleh admin,
         * 2. di-soft-delete otomatis oleh scheduler karena terlambat lebih
         *    lama dari `archiveGraceDays` (lihat config/meeldata.php).
        *
        * Memulihkan task WAJIB disertai tanggal baru yang masih di masa
        * depan - itulah makna "restore dengan mengganti hari".
    --}}

    {{--
        Callout + tombol kembali digabung satu baris. Sebelumnya tombolnya
        berada di baris sendiri sehingga menambah tinggi halaman tanpa
        perlu.
    --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        {{--
            `min-w-0` itu wajib: tanpa itu flex item memakai `min-width: auto`,
            teks panjang membuat baris ini melebar melebihi area konten sehingga
            tombol terdorong keluar layar.
        --}}
        <div
            role="status"
            class="flex min-w-0 flex-1 items-start gap-2.5 rounded-lg border border-line bg-surface-sunken px-3.5 py-2.5 text-[13px] text-ink-muted"
        >
            <x-icon name="trash" class="mt-px size-4 shrink-0" />
            <span class="leading-relaxed">
                @if ($archiveGraceDays > 0)
                    Tugas terlambat otomatis diarsipkan {{ $archiveGraceDays }} hari setelah deadline-nya lewat.
                @else
                    Tugas terlambat langsung diarsipkan begitu menjadi terlambat.
                @endif
                Saat dipulihkan, tanggalnya wajib diganti dengan tanggal yang belum lewat.
            </span>
        </div>

        <x-button variant="secondary" :href="route('admin.tasks.index')" class="shrink-0">
            <x-icon name="arrow-right" class="size-4 rotate-180" />
            Kembali ke Tugas
        </x-button>
    </div>

    <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
        @if ($tasks->isEmpty())
            <x-empty-state
                icon="trash"
                title="Sampah kosong"
                message="Belum ada tugas yang dihapus. Tugas terlambat otomatis akan muncul di sini setelah melewati masa tenggang."
                action-label="Kembali ke Tugas"
                :action-href="route('admin.tasks.index')"
            />
        @else
            {{--
                min-width memicu horizontal scroll di layar kecil (design.md §20).
                Angkanya longgar supaya tanggal, jam, dan sumber penghapusan
                tidak pernah pecah baris di tengah nilai.
            --}}
            <div class="table-scroll">
                {{--
                    `table-fixed` wajib di sini. Tanpa itu `table-layout: auto`
                    memperlakukan lebar kolom hanya sebagai saran, dan kolom
                    Tugas (deskripsi panjang) memakan hampir seluruh tabel sehingga
                    tanggal, counter, dan sumber penghapusan tergencet. Dengan
                    `table-fixed`, kolom lebar dipatuhi dan Tugas memakai sisa
                    ruang (isinya sudah `truncate`).

                    Lebar Status dan Dihapus diukur dari isi terpanjangnya,
                    bukan ditebak: "terlambat 118 hari 23 jam" + ikon butuh
                    ~150px dan "00:05 - otomatis oleh sistem" ~148px. Padding
                    sel 32px per sisi, jadi keduanya baru muat di kolom 192px.
                    Kolom yang lebih sempit membuat teks `whitespace-nowrap`
                    bocor ke sel sebelah - itulah yang membuat baris terlihat
                    bertabrakan.
                --}}
                <table class="w-full table-fixed min-w-[1040px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-line">
                            <th scope="col" class="table-header-cell">Tugas</th>
                            <th scope="col" class="table-header-cell w-40">Trainee</th>
                            <th scope="col" class="table-header-cell w-36">Deadline</th>
                            <th scope="col" class="table-header-cell w-48">Status</th>
                            <th scope="col" class="table-header-cell w-48">Dihapus</th>
                            <th scope="col" class="table-header-cell w-28 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        @foreach ($tasks as $task)
                            {{--
                                Task terarsip otomatis diberi aksen status yang
                                sama dengan badge-nya, supaya mudah dipindai:
                                merah = otomatis karena terlambat.
                            --}}
                            <tr
                                @class([
                                    'border-l-2 transition-colors hover:bg-surface-hover/60',
                                    'border-l-late bg-late-surface/30' => $task->deleted_by === null,
                                    'border-l-transparent bg-surface-sunken/40' => $task->deleted_by !== null,
                                ])
                            >
                                <td class="table-body-cell">
                                    <p class="truncate text-[13px] font-semibold text-ink" title="{{ $task->task }}">
                                        {{ $task->task }}
                                    </p>
                                    @if ($task->desc)
                                        <p class="truncate text-[12px] text-ink-faint" title="{{ $task->desc }}">
                                            {{ $task->desc }}
                                        </p>
                                    @endif
                                </td>

                                <td class="table-body-cell w-40">
                                    <span class="block truncate text-[13px] text-ink-soft" title="{{ $task->trainee?->name }}">
                                        {{ $task->trainee?->name ?? '—' }}
                                    </span>
                                </td>

                                {{-- Tanggal + jam: dua baris, tidak pernah pecah. --}}
                                <td class="table-body-cell w-36">
                                    <span class="block whitespace-nowrap text-ink-soft">
                                        {{ $task->deadline->translatedFormat('d M Y') }}
                                    </span>
                                    <span class="block whitespace-nowrap text-[12px] tabular-nums text-ink-faint">
                                        {{ $task->deadline->format('H:i') }}
                                    </span>
                                </td>

                                <td class="table-body-cell w-48">
                                    {{--
                                        Status `late` ditampilkan lewat badge statis
                                        (status milik sistem, tidak bisa diklik).
                                    --}}
                                    <x-status-badge :status="$task->status" />

                                    @if ($task->status === \App\Enums\TaskStatus::Late)
                                        {{--
                                            Waktu terlambat berjalan terus sejak
                                            deadline, dihitung dari waktu server
                                            (`serverNowIso`) sehingga tidak
                                            bergantung jam perangkat dan tidak
                                            nol saat refresh. Format panjang
                                            ("2 hari 3 jam") karena detik di
                                            sini hanya bising.
                                        --}}
                                        <span
                                            x-data="deadlineTicker(@js($task->deadline->toIso8601String()), @js($serverNowIso), false)"
                                            data-task-id="{{ $task->id }}"
                                            class="mt-1 flex items-center gap-1 whitespace-nowrap text-[11px] font-medium tabular-nums text-late"
                                        >
                                            <x-icon name="clock" class="size-3 shrink-0" />
                                            terlambat <span x-text="text"></span>
                                        </span>
                                    @endif
                                </td>

                                {{--
                                    Tanggal + jam di baris pertama (meniru kolom
                                    Deadline), sumber penghapusan di baris kedua
                                    sehingga baris ini punya lebar penuh untuk
                                    teksnya. Nama admin dipotong satu baris
                                    (dengan `title` untuk nilai penuh) supaya
                                    kolom ini tidak pernah meninggi.
                                --}}
                                <td class="table-body-cell w-48">
                                    <span class="block whitespace-nowrap text-ink-soft">
                                        {{ $task->deleted_at?->translatedFormat('d M Y') }}
                                        <span class="text-[12px] text-ink-faint">{{ $task->deleted_at?->format('H:i') }}</span>
                                    </span>
                                    {{-- Baris kedua khusus sumber penghapusan. Jam dipindah ke
                                         baris pertama supaya "otomatis oleh sistem"
                                         (114px) muat di dalam 160px area konten;
                                         kalau jam ikut di baris ini, teksnya
                                         tinggal ~114px dan langsung terpotong. --}}
                                    <span class="mt-0.5 block truncate text-[12px] text-ink-faint">
                                        @if ($task->deleted_by)
                                            <span title="{{ $task->deletedBy?->name }}">
                                                oleh {{ $task->deletedBy?->name ?? 'user' }}
                                            </span>
                                        @else
                                            <span class="font-medium text-late">otomatis oleh sistem</span>
                                        @endif
                                    </span>
                                </td>

                                <td class="table-body-cell w-28">
                                    <div class="flex items-center justify-end">
                                        <x-button
                                            variant="secondary"
                                            size="sm"
                                            :href="route('admin.tasks.restore.form', ['task' => $task->id])"
                                        >
                                            <x-icon name="arrow-right" class="size-4 rotate-180" />
                                            Pulihkan
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tasks->hasPages())
                <div class="border-t border-line px-4 py-3">
                    {{ $tasks->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection