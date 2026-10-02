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
    $confirmMessage = 'The task "'.$task->task.'" will be removed from the active list.';
@endphp

<span x-data="{ deleteOpen: false }">
    <x-confirm-modal
        title="Delete this task?"
        :message="$confirmMessage"
        confirm-label="Delete"
        busy-label="Deleting"
        tone="danger"
        open="deleteOpen"
        form-id="confirm-delete-{{ $task->id }}"
    >
        <x-slot:trigger>
            <button
                type="button"
                @click="deleteOpen = true"
                class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-late-surface hover:text-late focus-visible:outline-none"
                title="Delete task"
            >
                <x-icon name="trash" class="size-4" />
                <span class="sr-only">Delete {{ $task->task }}</span>
            </button>
        </x-slot:trigger>

        <x-slot:form>
            <form
                id="confirm-delete-{{ $task->id }}"
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