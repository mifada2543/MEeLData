@use('Illuminate\Support\Facades\Auth')
@use('App\Models\User')

@php
    /*
     * Tombol Edit pada baris tabel.
     *
     * Tiga kondisi, semuanya diputuskan server lewat
     * `TraineeTask::isEditableBy()`:
     *
     *  1. Terkunci  -> tidak dirender sama sekali. Menampilkan tombol yang
     *     pasti ditolak 403 hanya menambah frustrating; aturan mainnya ada di
     *     `TraineeTaskController::authorizeEdit()`.
     *  2. Perlu konfirmasi -> dialog dulu, baru form edit dibuka. Ini untuk
     *     task `done`/`canceled` yang deadline-nya belum terlewati.
     *  3. Normal -> tautan biasa.
     *
     * `readonly`/sembunyi bukan pengaman di sini; yang menolak tetap
     * controller. Konfirmasi murni untuk UX.
     */
    // `instance` membedakan render kartu mobile dan baris tabel untuk task yang
    // sama. Tanpa itu keduanya menghasilkan id form dialog yang identik -
    // id ganda membuat `aria-labelledby` ambigu dan `getElementById()` mengarah
    // ke dialog yang sedang tersembunyi.
    $instance = $instance ?? '';
    $formId = 'confirm-edit-'.($instance !== '' ? $instance.'-' : '').$task->id;

    /*
     * `Auth::user()` (facade) mengembalikan `Authenticatable|null` secara
     * deklaratif, sedangkan `isEditableBy()`/`editNeedsConfirmation()`
     * menerima `App\Models\User`. Guard `web` memakai model itu
     * (config/auth.php), jadi anotasi ini menyatakan kenyataan, bukan
     * mengubah perilaku apa pun.
     */
    /** @var User $actor */
    $actor = Auth::user();

    $editable = $task->isEditableBy($actor);
    $needsConfirm = $editable && $task->editNeedsConfirmation($actor);
    $editUrl = route($prefix.'tasks.edit', ['task' => $task->id]);

    // Pesan dialog disiapkan di variabel terpisah: ekspresi Blade pada
    // atribut komponen yang memuat `\"` akan merusak parsing tag komponen.
    $confirmMessage = 'Tugas "'.$task->task.'" sudah berstatus '.$task->status->label()
        .' dan deadline-nya belum terlewati. Isinya masih boleh diperbaiki, '
        .'tetapi status tidak akan berubah dari halaman ini.';
@endphp

@if (! $editable)
    {{-- Terkunci: sengaja tidak ada tombol apa pun. --}}
@elseif ($needsConfirm)
    <span x-data="{ editOpen: false }">
        <x-confirm-modal
            title="Yakin ingin mengubah ini?"
            :message="$confirmMessage"
            confirm-label="Lanjut ubah"
            busy-label="Membuka"
            open="editOpen"
            form-id="{{ $formId }}"
        >
            <x-slot:trigger>
                <button
                    type="button"
                    @click="editOpen = true"
                    class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline-none"
                    title="Edit task"
                >
                    <x-icon name="pencil" class="size-4" />
                    <span class="sr-only">Ubah {{ $task->task }}</span>
                </button>
            </x-slot:trigger>

            <x-slot:form>
                {{-- GET tanpa input: atribut form pada tombol konfirmasi membuat
                     browser membuka halaman edit. --}}
                <form id="{{ $formId }}" method="GET" action="{{ $editUrl }}"></form>
            </x-slot:form>
        </x-confirm-modal>
    </span>
@else
    <a
        href="{{ $editUrl }}"
        class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline-none"
        title="Edit task"
    >
        <x-icon name="pencil" class="size-4" />
        <span class="sr-only">Ubah {{ $task->task }}</span>
    </a>
@endif