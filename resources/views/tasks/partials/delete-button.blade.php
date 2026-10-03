@php
    // Individual soft delete (admin only) dengan confirmation dialog.
    //
    // `busy` berada di scope Alpine halaman (tasks/index) agar konsisten
    // dengan bulk delete. `deleteOpen` dideklarasikan di sini (satu scope
    // per baris tabel) supaya setiap dialog independen.
    //
    // Pesan konfirmasi disiapkan di variabel terpisah: ekspresi Blade
    // pada atribut komponen yang memuat karakter `\"` akan merusak
    // parsing tag komponen.
    $confirmMessage = 'Tugas "'.$task->task.'" akan dihapus dari daftar aktif.';

    // Sama seperti edit-button: kartu mobile dan baris tabel merender task yang
    // sama, jadi id form dialog harus dibedakan agar tidak kembar.
    $instance = $instance ?? '';
    $formId = 'confirm-delete-'.($instance !== '' ? $instance.'-' : '').$task->id;
@endphp

<span x-data="{ deleteOpen: false }">
    <x-confirm-modal
        title="Hapus tugas ini?"
        :message="$confirmMessage"
        confirm-label="Hapus"
        busy-label="Menghapus"
        tone="danger"
        open="deleteOpen"
        form-id="{{ $formId }}"
    >
        <x-slot:trigger>
            <button
                type="button"
                @click="deleteOpen = true"
                class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-late-surface hover:text-late focus-visible:outline-none"
                title="Hapus tugas"
            >
                <x-icon name="trash" class="size-4" />
                <span class="sr-only">Hapus {{ $task->task }}</span>
            </button>
        </x-slot:trigger>

        <x-slot:form>
            <form
                id="{{ $formId }}"
                method="POST"
                action="{{ route('admin.tasks.destroy', ['task' => $task->id]) }}"
                @submit="busy = true"
            >
                @csrf
                @method('DELETE')
            </form>
        </x-slot:form>
    </x-confirm-modal>
</span>