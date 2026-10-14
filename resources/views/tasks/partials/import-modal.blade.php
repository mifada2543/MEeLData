@php
    // Import modal (admin only, design.md §14).
    // Partial success: hasil import dikembalikan ke halaman list sebagai
    // flash `imported_count` + `import_errors`.
    //
    // Batas ukuran yang ditampilkan diambil dari konfigurasi PHP yang
    // benar-benar berlaku, bukan angka 10 MB yang dikunci di teks - supaya
    // label tidak menjanjikan ukuran yang pada akhirnya ditolak server.
    $maxMb = \App\Support\UploadLimits::effectiveLimitMb();
@endphp

<div
    x-data="{
        importOpen: false,
        importBusy: false,
        dragOver: false,
        fileName: '',
        fileSize: '',
        localError: '',

        /** Sinkronkan indikator dari input - sumber kebenaran satu-satunya. */
        syncFile() {
            const file = this.$refs.file?.files?.[0] ?? null;
            this.fileName = file ? file.name : '';
            this.fileSize = file ? this.humanSize(file.size) : '';
            if (file) this.localError = '';
        },

        humanSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
            if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
            return bytes + ' B';
        },

        /** Terapkan berkas hasil klik label maupun drag & drop. */
        useFiles(list) {
            if (!list || !list.length) return;
            try {
                this.$refs.file.files = list;
            } catch (e) {
                this.localError = 'Browser ini tidak mendukung penempelan berkas. Klik area untuk memilih berkas.';
                return;
            }
            this.syncFile();
        },

        onDrop(event) {
            this.dragOver = false;
            this.useFiles(event.dataTransfer?.files);
        },

        onBeforeSubmit(event) {
            if (!this.$refs.file.files.length) {
                this.localError = 'Silakan pilih berkas Excel terlebih dahulu.';
                this.importBusy = false;
                event.preventDefault();
                return;
            }
            this.localError = '';
            this.importBusy = true;
        },
    }"
    x-init="syncFile()"
>
    <x-button variant="secondary" @click="importOpen = true">
        <x-icon name="upload" class="size-4" />
        Impor
    </x-button>

    <div
        x-cloak
        x-show="importOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="import-title"
    >
        <div
            x-show="importOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]"
            @click="importOpen = false"
            aria-hidden="true"
        ></div>

        <div
            x-show="importOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-[0.99]"
            x-trap.noscroll="importOpen"
            @keydown.escape.window="importOpen = false"
            class="relative w-full max-w-lg overflow-hidden rounded-xl border border-line bg-surface shadow-lg"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 id="import-title" class="text-[15px] font-semibold text-ink">Impor Tugas</h2>
                <button
                    type="button"
                    @click="importOpen = false"
                    class="-m-1.5 rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                    aria-label="Tutup impor"
                >
                    <x-icon name="x" class="size-4" />
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.tasks.import') }}"
                enctype="multipart/form-data"
                class="p-6"
                @submit="onBeforeSubmit($event)"
            >
                @csrf

                <label for="import-file" class="block text-[13px] font-medium text-ink-soft">Unggah Berkas Excel</label>

                <label
                    for="import-file"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop($event)"
                    :class="dragOver ? 'border-accent bg-surface-hover' : 'border-line-strong bg-surface-sunken'"
                    class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-6 py-8 text-center transition-colors hover:border-accent hover:bg-surface-hover"
                >
                    <x-icon name="document" class="size-6 text-ink-faint" />
                    <span class="text-[13px] font-medium text-ink">
                        <span x-text="importBusy ? 'Mengimpor...' : (fileName ? 'Klik untuk mengganti berkas' : 'Pilih berkas atau letakkan di sini')"></span>
                    </span>
                    <span class="text-xs text-ink-faint">Format didukung: .xlsx · maks. {{ $maxMb }} MB</span>
                </label>

                {{-- Tanpa atribut `required`: input berada di posisi tersembunyi,
                     sehingga pesan validasi browser sulit terlihat. Kekosongan
                     ditangani di onBeforeSubmit() dengan pesan yang terlihat,
                     dan tetap dijaga oleh validasi server. --}}
                <input
                    id="import-file"
                    name="file"
                    type="file"
                    accept=".xlsx"
                    class="sr-only"
                    x-ref="file"
                    @change="syncFile()"
                    @input="syncFile()"
                >

                {{-- Indikator berkas terpilih: sumbernya `input.files`, sehingga
                     selalu mencerminkan apa yang benar-benar akan dikirim. --}}
                <div class="mt-2.5">
                    <div class="flex items-start justify-between gap-3 text-xs">
                        <div class="min-w-0 flex-1">
                            <div
                                x-show="fileName"
                                x-cloak
                                class="flex items-center gap-2 rounded-lg border border-done-border bg-surface px-3 py-2"
                                role="status"
                            >
                                <x-icon name="check-circle" class="size-4 shrink-0 text-done" />
                                <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-ink" x-text="fileName"></span>
                                <span class="shrink-0 text-[11px] tabular-nums text-ink-faint" x-text="fileSize"></span>
                                <button
                                    type="button"
                                    @click="$refs.file.value = ''; syncFile();"
                                    class="-m-1 shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                                    aria-label="Hapus berkas terpilih"
                                >
                                    <x-icon name="x" class="size-3.5" />
                                </button>
                            </div>

                            <p x-show="!fileName" class="font-medium text-ink-faint">Belum ada berkas dipilih</p>
                        </div>

                        <span class="shrink-0 text-ink-faint">Status valid: progress, done, canceled</span>
                    </div>

                    <p
                        x-show="localError"
                        x-cloak
                        class="mt-1.5 flex items-start gap-1.5 text-[13px] leading-relaxed text-late"
                        role="alert"
                    >
                        <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />
                        <span x-text="localError"></span>
                    </p>
                </div>

                <x-field-error name="file" />

                <div class="mt-6 flex items-center justify-between gap-3">
                    <a
                        href="{{ route('admin.tasks.import.template') }}"
                        class="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-soft underline-offset-4 transition-colors hover:text-ink hover:underline"
                    >
                        <x-icon name="download" class="size-3.5" />
                        Unduh Template
                    </a>

                    <div class="flex gap-2.5">
                        <x-button variant="secondary" @click="importOpen = false" x-bind:disabled="importBusy">
                            Batal
                        </x-button>
                        <x-button type="submit" x-bind:disabled="importBusy">
                            <span x-show="!importBusy">Impor</span>
                            <span x-cloak x-show="importBusy">Mengimpor...</span>
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
