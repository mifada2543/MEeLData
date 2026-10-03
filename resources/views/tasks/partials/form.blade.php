@use('Illuminate\Support\Facades\Auth')

@php
    /*
     * `Auth::user()` (facade) dipakai, bukan `auth()->user()` (helper):
     * method helper diteruskan lewat `__call`, sehingga analis statis
     * tidak dapat melihat `user()`. Guard `web` memakai
     * `App\Models\User` (config/auth.php).
     */
    /** @var \App\Models\User|null $currentUser */
    $currentUser = Auth::user();

    // Form bersama untuk Create & Edit (design.md §9 & §11).
    //
    // Catatan penting: input `disabled` (trainee) TIDAK ikut terkirim ke
    // server. Backend tetap memaksa trainee_id dari authenticated user
    // (lihat StoreTaskRequest::taskAttributes).
    //
    // Task `late` punya mode khusus: hanya `deadline` yang boleh diubah.
    // Field lain di-render sebagai teks mati (bukan input), jadi form tetap
    // submit payload normal - dan `UpdateTaskRequest` tetap memangkas payload
    // ke `deadline` saja walau request dibuat-buat.
    $dateTimeFormat = 'Y-m-d\TH:i';
    $locked = $task->exists && $task->hasLockedEditFields();
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $action }}"
    x-data="{ busy: false, confirmOpen: false }"
    @submit="busy = true"
>
    @csrf
    @unless ($task->exists)
        {{-- Create --}}
    @else
        @method('PUT')
    @endunless

    <div class="space-y-5">
        {{--
            Penanda bahwa form ini dalam mode "hanya deadline". Tanpa
            catatan ini, lima field yang tiba-tiba tidak bisa diketik akan
            terasa seperti bug, bukan aturan - padahal justru inilah
            satu-satunya jalan keluar dari status "Terlambat".
        --}}
        @if ($locked)
            <div class="flex items-start gap-2.5 rounded-lg border border-late/25 bg-late-surface px-3.5 py-3">
                <x-icon name="shield" class="mt-px size-4 shrink-0 text-late" />
                <p class="text-[12.5px] leading-relaxed text-ink-soft">
                    Tugas ini sudah <span class="font-semibold">terlambat</span>, jadi isinya dikunci.
                    Satu-satunya yang bisa diubah adalah <span class="font-semibold">tanggal deadline</span>.
                    Geser ke masa depan dan statusnya otomatis kembali menjadi
                    &quot;Sedang Dikerjakan&quot;.
                </p>
            </div>
        @endif

        {{-- Trainee --}}
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <label for="trainee_id" class="block text-[13px] font-medium text-ink-soft">Trainee</label>

                @unless ($isAdmin)
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-faint">
                        <x-icon name="shield" class="size-3" />
                        terkunci ke akun Anda
                    </span>
                @endunless
            </div>

            @if ($isAdmin)
                <div class="relative mt-1.5">
                    <select
                        id="trainee_id"
                        name="trainee_id"
                        required
                        @disabled($locked)
                        class="h-10 w-full appearance-none rounded-md border border-line-strong bg-surface pl-3 pr-9 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-soft"
                    >
                        <option value="">Pilih trainee</option>
                        @foreach ($trainees as $trainee)
                            <option
                                value="{{ $trainee->id }}"
                                @selected((int) old('trainee_id', $task->trainee_id) === $trainee->id)
                            >
                                {{ $trainee->name }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-ink-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </div>
            @else
                <div class="mt-1.5 flex h-10 items-center gap-2.5 rounded-md border border-line bg-surface-sunken px-3">
                    <x-icon name="user" class="size-4 shrink-0 text-ink-faint" />
                    <input
                        id="trainee_id"
                        type="text"
                        value="{{ $currentUser?->name }}"
                        disabled
                        class="min-w-0 flex-1 cursor-not-allowed bg-transparent text-sm text-ink-soft"
                    >
                </div>
            @endif

            <x-field-error name="trainee_id" />
        </div>

        {{-- Task --}}
        <div>
            <label for="task" class="block text-[13px] font-medium text-ink-soft">Tugas</label>
            <input
                id="task"
                name="task"
                type="text"
                value="{{ old('task', $task->task) }}"
                required
                maxlength="255"
                @disabled($locked)
                placeholder="Contoh: Perbaikan Kabel Jaringan"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-soft"
            >
            <x-field-error name="task" />
        </div>

        {{-- Description --}}
        <div>
            <label for="desc" class="block text-[13px] font-medium text-ink-soft">Deskripsi</label>
            <textarea
                id="desc"
                name="desc"
                rows="4"
                @disabled($locked)
                placeholder="Keterangan tambahan (opsional)"
                class="mt-1.5 w-full resize-y rounded-md border border-line-strong bg-surface px-3 py-2.5 text-sm leading-relaxed text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-soft"
            >{{ old('desc', $task->desc) }}</textarea>
            <x-field-error name="desc" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            {{-- Start date --}}
            <div>
                <label for="start_date" class="block text-[13px] font-medium text-ink-soft">Tanggal Mulai</label>
                <input
                    id="start_date"
                    name="start_date"
                    type="datetime-local"
                    value="{{ old('start_date', $task->start_date?->format($dateTimeFormat)) }}"
                    {{-- Pada form create tanggal lampau memang dilarang.
                         Pada form edit TIDAK diberi min: nilai lama yang sudah
                         lewat boleh dipertahankan, dan `min` akan membuat
                         field itu tidak bisa dikirim apa adanya. --}}
                    @if (! $task->exists) min="{{ now()->format('Y-m-d\TH:i') }}" @endif
                    @disabled($locked)
                    required
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-soft"
                >
                <x-field-error name="start_date" />
            </div>

            {{-- Deadline --}}
            <div>
                <label for="deadline" class="block text-[13px] font-medium text-ink-soft">Tanggal Deadline</label>
                <input
                    id="deadline"
                    name="deadline"
                    type="datetime-local"
                    value="{{ old('deadline', $task->deadline?->format($dateTimeFormat)) }}"
                    @if (! $task->exists) min="{{ now()->format('Y-m-d\TH:i') }}" @endif
                    required
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="deadline" />
            </div>
        </div>

        {{-- Status saat ini (diubah lewat inline update dari tabel) --}}
        @if ($task->exists)
            <div class="flex items-start justify-between gap-3 rounded-lg border border-line bg-surface-sunken px-4 py-3">
                <div class="min-w-0">
                    <p class="text-[13px] font-medium text-ink-soft">Status saat ini</p>
                    @if ($locked)
                        {{--
                            Penjelasan lengkap soal aturan deadline sudah ada di
                            callout paling atas; di sini cukup satu kalimat
                            supaya tidak mengulang hal yang sama dua kali.
                        --}}
                        <p class="mt-0.5 text-xs text-ink-muted">
                            Status &quot;Terlambat&quot; diatur otomatis oleh sistem.
                        </p>
                    @elseif ($task->status->isSystemOwned())
                        <p class="mt-0.5 text-xs text-ink-faint">
                            Status &quot;Terlambat&quot; diatur otomatis oleh sistem dan tidak dapat diubah.
                        </p>
                    @else
                        <p class="mt-0.5 text-xs text-ink-faint">Ubah status langsung dari daftar tugas.</p>
                    @endif
                </div>
                <x-status-badge :status="$task->status" class="shrink-0" />
            </div>
        @endif
    </div>

    {{--
        Aksi:
        - Cancel  : membatalkan tanpa menyimpan, kembali ke daftar task.
        - Submit : membuka konfirmasi lebih dulu sebelum mengirim.
    --}}
    <div class="mt-7 flex items-center justify-end gap-2.5 border-t border-line pt-5">
        {{--
            Cancel tidak lagi memakai form.reset(): pada form Create yang
            baru dibuka tidak ada yang terlihat berubah sehingga tombolnya
            terasa tidak berfungsi. Sekarang keduanya membatalkan dan
            kembali ke daftar task tanpa menyimpan.
        --}}
        <x-button
            variant="secondary"
            :href="route($prefix.'tasks.index')"
            x-bind:disabled="busy"
        >
            Batal
        </x-button>

        {{-- Submit pertama membuka konfirmasi, bukan langsung mengirim. --}}
        <x-button type="button" @click="confirmOpen = true" x-bind:disabled="busy">
            <span x-show="!busy">{{ $submitLabel }}</span>
            <span x-cloak x-show="busy">Saving...</span>
        </x-button>
    </div>

    {{-- Submit confirmation (design.md §10): konfirmasi sebelum data dikirim. --}}
    <x-confirm-modal
        title="Simpan tugas ini?"
        message="Apakah Anda yakin ingin menyimpan tugas ini?"
        confirm-label="Simpan"
        busy-label="Saving"
        open="confirmOpen"
        form-id="{{ $formId }}"
    />
</form>