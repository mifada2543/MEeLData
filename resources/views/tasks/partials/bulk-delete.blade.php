@php
    // Bulk delete (admin only).
    //
    // Checkbox pada tabel berada di luar form, tetapi tetap terkirim karena
    // memakai atribut form="bulk-delete-form" (HTML5). `selected` dan `busy`
    // berada di scope Alpine halaman (tasks/index); `bulkDeleteOpen`
    // dideklarasikan di sini agar tidak bentrok antar-instance.
@endphp

<div x-data="{ bulkDeleteOpen: false }">
    <x-confirm-modal
        title="Hapus tugas terpilih?"
        confirm-label="Hapus"
        busy-label="Menghapus"
        tone="danger"
        open="bulkDeleteOpen"
        form-id="bulk-delete-form"
    >
        <x-slot:trigger>
            <x-button variant="dangerSoft" @click="bulkDeleteOpen = true" x-bind:disabled="selected.length === 0">
                <x-icon name="trash" class="size-4" />
                <span x-show="selected.length > 0">
                    Hapus Terpilih
                    <span class="rounded-full bg-late/10 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums" x-text="selected.length"></span>
                </span>
                <span x-show="selected.length === 0">Hapus Terpilih</span>
            </x-button>
        </x-slot:trigger>

        <x-slot:form>
            <p class="mt-3 flex items-center gap-2 rounded-md bg-late-surface px-3 py-2.5 text-[13px] font-medium text-late">
                <x-icon name="alert" class="size-4 shrink-0" />
                <span><span class="tabular-nums" x-text="selected.length"></span> tugas akan dihapus.</span>
            </p>

            <form
                id="bulk-delete-form"
                method="POST"
                action="{{ route('admin.tasks.bulk-delete') }}"
                @submit="busy = true"
            >
                @csrf
            </form>
        </x-slot:form>
    </x-confirm-modal>
</div>

@if ($errors->has('ids'))
    <p class="mt-2 flex items-center gap-1.5 text-[13px] text-late">
        <x-icon name="alert" class="size-3.5" />
        {{ $errors->first('ids') }}
    </p>
@endif