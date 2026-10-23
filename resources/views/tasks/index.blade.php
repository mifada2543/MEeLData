@extends('layouts.app')

@use('Illuminate\Support\Facades\Auth')
@use('App\Models\User')

@php
    $isAdmin = User::isAdminActor(Auth::user());
    $prefix = $isAdmin ? 'admin.' : '';
    $total = $tasks->total();

    /*
     * Urutan prioritas 5 tingkat (lihat TraineeTask::scopeByWorkPriority).
     * Label ikut ditampilkan per kelompok, dan kelompok 4 diberi warna
     * peringatan karena isinya menetap hanya bila scheduler tidak aktif.
     */
    $priorityLabels = [
        1 => 'Sedang Dikerjakan',
        2 => 'Selesai',
        3 => 'Dibatalkan',
        4 => 'Lewat Deadline — Menunggu Penandaan',
        5 => 'Terlambat',
    ];

    // Jumlah kolom untuk baris label kelompok: 7 di panel admin, 6 di trainee.
    $colSpan = $isAdmin ? 7 : 6;
    $previousPriority = null;
@endphp

@section('title', $isAdmin ? 'Tugas' : 'Tugas Saya')
@section('heading', $isAdmin ? 'Tugas' : 'Tugas Saya')
@section('subheading', $total.' '.Str::plural('tugas', $total).' · '.($isAdmin ? 'semua trainee' : 'ditugaskan kepada Anda'))

@section('content')
<div
    x-init="startLive(@js(route($prefix.'tasks.live')), @js($serverNowIso), @js($overdueIds))"
    x-data="{
        selected: [],
        busy: false,
        exportOpen: false,
        exporting: false,
        filterUrl: @js(route($prefix.'tasks.export', $status ? ['status' => $status] : [])),
        /*
         * Selected menang: bila ada baris dipilih, export hanya baris itu.
         * Bila tidak ada, export mengikuti filter status yang aktif.
         */
        get exportUrl() {
            if (this.selected.length === 0) return this.filterUrl
            const params = this.selected.map((id) => 'ids[]=' + encodeURIComponent(id)).join('&')
            return @js(route($prefix.'tasks.export')) + '?' + params
        },
        /*
         * Ekspor hanya berjalan setelah pengguna mengonfirmasi dialog.
         */
        startExport() {
            const url = this.exportUrl
            this.exporting = true
            this.exportOpen = false
            // Download dipicu lewat anchor sementara agar halaman tidak
            // dimuat ulang.
            const link = document.createElement('a')
            link.href = url
            link.rel = 'noopener'
            document.body.appendChild(link)
            link.click()
            link.remove()
            setTimeout(() => { this.exporting = false }, 1200)
        },
        toggleAll(checked) {
            this.selected = checked
                ? Array.from(document.querySelectorAll('.bulk-checkbox')).map((el) => el.value)
                : []
        },
    }"
>
    {{-- Action bar: import di kiri, aksi utama di kanan --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            {{--
                Tombol "Filter" (modal dropdown status) DIHAPUS. Isinya
                persis sama dengan chip status di bawah - tidak ada pencarian
                atau filter lain di dalamnya - jadi dua kontrol untuk satu
                hal yang sama, dan di HP keduanya memakan tempat. Chip
                menang karena jumlah per kelompok langsung terlihat tanpa
                harus diklik. Lihat resources/views/tasks/partials/status-filter.blade.php
            --}}
            @include('tasks.partials.import-modal')
        </div>

        {{-- Filter status + jumlah asli per kelompok. --}}
        @include('tasks.partials.status-filter')

        {{--
            `flex-wrap` WAJIB di baris ini, dan bukan sekadar rapih: ada
            lima tombol (Ekspor, Hapus, Sampah, Tambah) yang totalnya lebih
            lebar dari viewport ponsel. Tanpa wrap, flex item menolak menyusut
            sehingga baris meluber ~113px dan seluruh halaman ikut
            ter-scroll horizontal.
        --}}
        <div class="flex flex-wrap items-center gap-2">
            {{--
                Ekspor terlihat langsung, tidak disembunyikan di modal.
                Bila ada baris dicentang, hanya baris itu yang diekspor;
                bila tidak, seluruh hasil filter aktif.
                Ekspor meminta konfirmasi lebih dulu.
            --}}
            <button
                type="button"
                @click="exportOpen = true"
                class="inline-flex h-9.5 items-center justify-center gap-2 rounded-md border border-line-strong bg-surface px-3.5 text-sm font-medium text-ink transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2"
            >
                <x-icon name="download" class="size-4 shrink-0" />
                <span x-show="selected.length === 0">Ekspor</span>
                <span x-cloak x-show="selected.length > 0">
                    Ekspor Terpilih
                    <span class="rounded-full bg-accent-soft px-1.5 py-0.5 text-[11px] font-semibold tabular-nums" x-text="selected.length"></span>
                </span>
            </button>

            {{-- Konfirmasi export --}}
            <div
                x-cloak
                x-show="exportOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="export-confirm-title"
                @keydown.escape.window="exportOpen = false"
            >
                <div
                    x-show="exportOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]"
                    @click="exportOpen = false"
                    aria-hidden="true"
                ></div>

                <div
                    x-show="exportOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-1 scale-[0.99]"
                    x-trap.noscroll="exportOpen"
                    class="relative w-full max-w-md overflow-hidden rounded-xl border border-line bg-surface shadow-lg"
                >
                    <div class="p-6">
                        <div class="flex items-start gap-3.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-ink">
                                <x-icon name="download" class="size-[18px]" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <h2 id="export-confirm-title" class="text-[15px] font-semibold text-ink">
                                    Ekspor tugas ke Excel?
                                </h2>

                                {{-- Pesan menyesuaikan mode export yang aktif --}}
                                <p class="mt-1.5 text-[13px] leading-relaxed text-ink-muted" x-cloak x-show="selected.length === 0">
                                    @if ($status)
                                        Ekspor seluruh tugas <span class="font-medium text-ink">{{ \App\Enums\TaskStatus::from($status)->label() }}</span> yang dapat Anda lihat.
                                    @else
                                        Ekspor seluruh tugas aktif yang dapat Anda lihat.
                                    @endif
                                </p>

                                {{--
                                    Kedua <span> sengaja berdempet tanpa spasi/enter di
                                    antaranya. Jumlah tugas dan sisa kalimat dirangkai
                                    di sisi klien, sehingga spasi sebelum tanda baca
                                    ikut dikendalikan (tanpa ini tampil
                                    "1 tugas yang dipilih . Filter ...").
                                --}}
                                <p class="mt-1.5 text-[13px] leading-relaxed text-ink-muted" x-cloak x-show="selected.length > 0">
                                    Hanya ekspor <span class="font-medium tabular-nums text-ink" x-text="selected.length">0</span><span x-text="selected.length === 1 ? ' tugas yang dipilih.' : ' tugas yang dipilih ini.'"> tugas yang dipilih.</span>
                                    Filter status yang aktif diabaikan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2.5 rounded-b-xl border-t border-line bg-surface-sunken px-6 py-4">
                        <x-button variant="secondary" @click="exportOpen = false" x-bind:disabled="exporting">
                            Batal
                        </x-button>
                        <x-button type="button" @click="startExport()" x-bind:disabled="exporting">
                            <span x-show="!exporting">Ekspor</span>
                            <span x-cloak x-show="exporting">Menyiapkan...</span>
                        </x-button>
                    </div>
                </div>
            </div>

            @if ($isAdmin)
                @include('tasks.partials.bulk-delete')

                {{--
                    Pintu masuk halaman Sampah. Task terarsip (termasuk yang
                    di-soft-delete otomatis karena terlambat) hanya terlihat
                    di sini.
                --}}
                <a
                    href="{{ route('admin.tasks.trash') }}"
                    class="inline-flex h-9.5 items-center justify-center gap-2 rounded-md border border-line-strong bg-surface px-3.5 text-sm font-medium text-ink transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2"
                >
                    <x-icon name="trash" class="size-4 shrink-0" />
                    <span class="hidden sm:inline">Sampah</span>
                    <span class="sr-only sm:hidden">Sampah</span>
                </a>
            @endif

            <x-button :href="route($prefix.'tasks.create')">
                <x-icon name="plus" class="size-4" />
                Tambah Tugas
            </x-button>
        </div>
    </div>

    {{-- Import result (partial success reporting) --}}
    @if (session('imported_count') !== null || count(session('import_errors', [])) > 0)
        @include('tasks.partials.import-result')
    @endif

    {{-- Table: surface utama aplikasi (prinsip "table-first") --}}
    {{--
        Banner kondisi bermasalah. Dua kondisi ditampilkan terpisah karena
        penanganannya berbeda:
        - `pendingLateCount` = scheduler belum berjalan (gejala rusak),
        - `lateCount` = informational, akan diarsipkan otomatis.
    --}}
    {{--
        Peringatan scheduler mati memakai `schedulerDownCount`, BUKAN
        `pendingLateCount`. Yang kedua menghitung seluruh progress yang lewat
        deadline - termasuk yang masih di dalam jendela 120 detik, yaitu
        kondisi normal. Kalau dipakai di sini, setiap task yang baru lewat
        deadline akan memicu peringatan palsu.

        Angka di dalam `x-text` agar ikut ter-update dari polling.
    --}}
    {{--
       kedua banner di bawah hanya untuk admin: isinya kondisi sistem
        (cron mati, jumlah task yang akan diarsipkan), bukan informasi tugas
        milik pengguna. Trainee cukup melihat badge status di tasknya sendiri.
    --}}
    @if ($isAdmin)
        <div
            x-cloak
            x-show="$store.live.down > 0"
            role="status"
            class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 rounded-lg border border-late-border bg-late-surface px-3.5 py-2.5 text-[13px] text-late"
        >
            <x-icon name="alert" class="size-4 shrink-0" />
            <span>
                <span x-text="$store.live.down"></span>
                tugas lewat deadline tapi belum ditandai — scheduler mungkin tidak aktif.
            </span>
            <a
                href="{{ route($prefix.'tasks.index', ['status' => 'progress']) }}"
                class="font-medium underline underline-offset-2 hover:no-underline"
            >
                Lihat
            </a>
        </div>

        @if ($lateCount > 0)
            <div
                role="status"
                class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 rounded-lg border border-line bg-surface-sunken px-3.5 py-2.5 text-[13px] text-ink-muted"
            >
                <x-icon name="clock" class="size-4 shrink-0 text-late" />
                <span>
                    {{ $lateCount }} {{ Str::plural('tugas', $lateCount) }} terlambat
                    @if ($archiveGraceDays > 0)
                        · otomatis diarsipkan dalam {{ $archiveGraceDays }} hari
                    @endif
                </span>
                <a
                    href="{{ route($prefix.'tasks.index', ['status' => 'late']) }}"
                    class="font-medium text-ink underline underline-offset-2 hover:no-underline"
                >
                    Tampilkan
                </a>
            </div>
        @endif
    @endif

    <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
        @if ($tasks->isEmpty())
            @if ($status)
                <x-empty-state
                    icon="filter"
                    title="Tidak ada tugas yang cocok dengan filter"
                    message="Tidak ada tugas dengan status ini. Coba status lain atau atur ulang filter."
                    action-label="Tambah Tugas"
                    :action-href="route($prefix.'tasks.create')"
                />
            @else
                <x-empty-state
                    title="Belum ada tugas"
                    message="Belum ada tugas sama sekali. Buat tugas pertama untuk memulai."
                    action-label="Tambah Tugas"
                    :action-href="route($prefix.'tasks.create')"
                />
            @endif
        @else
            {{--
                Bentuk mobile: satu kartu per task.

                Tabel perlu 740px (trainee) / 1000px (admin). Di HP 390px
                hanya ~48% kolomnya yang muat, jadi tanggal dan status -
                justru yang paling penting - terpotong di kanan. Kartu
                mengubah setiap kolom jadi pasangan label -> nilai sehingga
                tidak ada yang perlu digeser.
            --}}
            <div class="md:hidden">
                @foreach ($tasks as $task)
                    @include('tasks.partials.task-card', ['task' => $task])
                    @php $previousPriority = $task->workPriority(); @endphp
                @endforeach
            </div>

            {{--
                Bentuk desktop/tablet: tabel.

                `md:block` - bukan `block` - supaya tabel tidak pernah dirender
                di layar sempit yang sudah pakai kartu di atas.
            --}}
            <div class="hidden md:block">
            {{--
                Petunjuk scroll hanya ditampilkan kalau tabel MEMANG lebih
                lebar dari wadahnya: tabel trainee (740px) sudah muat di
                breakpoint `md` (768px), sedangkan tabel admin (1000px)
                masih meluber sampai `lg`.
            --}}
            <p @class([
                'flex items-center gap-1.5 border-b border-line bg-surface-sunken px-4 py-2 text-[11.5px] text-ink-muted',
                'lg:hidden' => $isAdmin,
                'hidden' => ! $isAdmin,
            ])>
                <svg class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M12.79 14.77a.75.75 0 0 0 .02 1.06l4.5 4.25a.75.75 0 1 0 1.08-1.04l-4.5-4.25a.75.75 0 0 0-1.1.02Z" clip-rule="evenodd" />
                </svg>
                Geser ke samping untuk melihat semua kolom
            </p>

            {{-- `table-scroll` = overflow-x-auto + contain:paint, lihat app.css --}}
            <div class="table-scroll">
                {{--
                    min-width memicu horizontal scroll di layar kecil
                    (design.md §20). Deskripsi disembunyikan di bawah `sm`
                    karena bersifat sekunder dan menyulitkan pemindaian.
                --}}
                <table class="w-full border-collapse text-sm {{ $isAdmin ? 'min-w-[1000px]' : 'min-w-[740px]' }}">
                    <thead>
                        <tr class="border-b border-line">
                            {{--
                                Kolom checkbox tersedia untuk admin (bulk delete)
                                maupun trainee (export selected). Label dibuat berbeda
                                agar trainee tidak mengira ini aksi delete.
                            --}}
                            <th scope="col" class="table-header-cell w-11">
                                <input
                                    type="checkbox"
                                    @change="toggleAll($event.target.checked)"
                                    class="size-4 rounded border-line-strong text-accent accent-[var(--color-accent)] focus-visible:outline-none"
                                    aria-label="{{ $isAdmin ? 'Pilih semua tugas untuk aksi massal' : 'Pilih semua tugas untuk diekspor' }}"
                                >
                            </th>

                            @if ($isAdmin)
                                <th scope="col" class="table-header-cell w-48">Trainee</th>
                            @endif

                            <th scope="col" class="table-header-cell">Tugas</th>
                            <th scope="col" class="table-header-cell w-36">Mulai</th>
                            <th scope="col" class="table-header-cell w-36">Deadline</th>
                            <th scope="col" class="table-header-cell w-40">Status</th>
                            <th scope="col" class="table-header-cell w-28 text-right">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        @php $previousPriority = null; @endphp
                        @foreach ($tasks as $task)
                            @php
                                $priority = $task->workPriority();
                            @endphp

                            @if ($priority !== $previousPriority)
                                @php $previousPriority = $priority; @endphp
                                <tr class="bg-surface-sunken/60">
                                    <td colspan="{{ $colSpan }}" class="px-4 py-2">
                                        <span
                                            @class([
                                                'text-[11px] font-semibold uppercase tracking-wide',
                                                'text-late' => $priority === 4,
                                                'text-ink-faint' => $priority !== 4,
                                            ])
                                        >
                                            {{ $priorityLabels[$priority] }}
                                        </span>
                                    </td>
                                </tr>
                            @endif

                            <tr
                                @class([
                                    'group/row border-l-2 transition-colors hover:bg-surface-hover/60',
                                    'border-l-late bg-late-surface/50' => $priority === 5,
                                    'border-l-late/50 bg-late-surface/20' => $priority === 4,
                                    'border-l-transparent' => $priority < 4,
                                ])
                            >
                                {{--
                                    Checkbox tersedia untuk kedua role.
                                    `form="bulk-delete-form"` hanya relevan
                                    untuk admin; trainee memakai checkbox yang
                                    sama untuk menyusun "Ekspor Terpilih".
                                --}}
                                <td class="table-body-cell w-11">
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="{{ $task->id }}"
                                        @if ($isAdmin) form="bulk-delete-form" @endif
                                        x-model="selected"
                                        class="bulk-checkbox size-4 rounded border-line-strong accent-[var(--color-accent)] focus-visible:outline-none"
                                        aria-label="{{ $isAdmin ? 'Pilih tugas untuk aksi massal' : 'Pilih tugas untuk diekspor' }}: {{ $task->task }}"
                                    >
                                </td>

                                @if ($isAdmin)
                                    <td class="table-body-cell w-48">
                                        <span class="font-medium text-ink">{{ $task->trainee?->name ?? '—' }}</span>
                                    </td>
                                @endif

                                <td class="table-body-cell">
                                    <p class="font-medium text-ink">{{ $task->task }}</p>
                                    @if ($task->desc)
                                        <p class="mt-0.5 hidden max-w-sm truncate text-[12.5px] text-ink-muted sm:block">{{ $task->desc }}</p>
                                    @endif
                                </td>

                                <td class="table-body-cell w-36 whitespace-nowrap text-ink-muted">
                                    {{ $task->start_date->translatedFormat('d M Y') }}
                                    <span class="block text-[12px] text-ink-faint">{{ $task->start_date->format('H:i') }}</span>
                                </td>

                                <td class="table-body-cell w-36 whitespace-nowrap">
                                    <span class="text-ink-soft">{{ $task->deadline->translatedFormat('d M Y') }}</span>
                                    <span class="block text-[12px] text-ink-faint">{{ $task->deadline->format('H:i') }}</span>

                                    {{--
                                        Kelompok 4: hitungan mundur realtime
                                        "sudah lewat …" memakai waktu server
                                        (lihat resources/js/live.js). Begitu
                                        scheduler menandai `late`, badge
                                        Terlambat menggantikan counter tanpa
                                        reload halaman.
                                    --}}
                                    @if ($task->hasCountdown())
                                        {{--
                                            Hitung mundur realtime untuk
                                            SETIAP task `progress`, dengan tiga
                                            bentuk (lihat resources/js/live.js):

                                            * `sisa 14:32`   deadline belum
                                              lewat -> menuju deadline
                                            * `dalam 0:45`  deadline lewat,
                                              masih dalam jendela penandaan
                                            * `terlambat 4 menit` jendela
                                              habis tapi masih progress ->
                                              scheduler tidak jalan

                                            Hanya kelompok 4 punya badge
                                            Terlambat pengganti, jadi kedua
                                            keadaan ini HARUS berada di dalam
                                            elemen yang sama yang membawa
                                            `x-data` (nilai `done` hanya ada
                                            di scope itu).
                                        --}}
                                        <div
                                            x-data="deadlineTicker(@js($task->deadline->toIso8601String()), @js($serverNowIso), true)"
                                            data-task-id="{{ $task->id }}"
                                        >
                                            <span
                                                x-show="!done"
                                                class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium tabular-nums text-late"
                                            >
                                                <x-icon name="clock" class="size-3" />
                                                <span x-text="text"></span>
                                            </span>

                                            <span x-cloak x-show="done" class="mt-1 block">
                                                <x-status-badge :status="\App\Enums\TaskStatus::Late" size="sm" />
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td class="table-body-cell w-40">
                                    {{--
                                        Status milik sistem (`Terlambat`) dirender sebagai
                                        badge statis: tidak ada pemicu, chevron, atau
                                        dropdown, sehingga tidak ada affordance yang
                                        menjanjikan aksi. Aturan server-side ada di
                                        UpdateStatusRequest.
                                    --}}
                                    @if ($task->status->isSystemOwned())
                                        <x-status-badge
                                            :status="$task->status"
                                            title="Status &quot;Terlambat&quot; diatur otomatis oleh sistem dan tidak dapat diubah."
                                        />
                                    @else
                                        {{-- Badge sekaligus pemicu dropdown (design.md §8.1). --}}
                                        <x-status-select :task="$task" :route-prefix="$prefix" instance="table" />
                                    @endif
                                </td>

                                <td class="table-body-cell w-28">
                                    <div class="flex items-center justify-end gap-1">
                                        @include('tasks.partials.edit-button', ['task' => $task, 'instance' => 'table'])

                                        @if ($isAdmin)
                                            @include('tasks.partials.delete-button', ['task' => $task, 'instance' => 'table'])
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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