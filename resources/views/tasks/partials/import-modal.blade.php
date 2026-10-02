@php
    // Import modal (admin only, design.md §14).
    // Partial success: hasil import dikembalikan ke halaman list sebagai
    // flash `imported_count` + `import_errors`.
@endphp

<div x-data="{ importOpen: false, importBusy: false, fileName: '' }">
    <x-button variant="secondary" @click="importOpen = true">
        <x-icon name="upload" class="size-4" />
        Import
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
                <h2 id="import-title" class="text-[15px] font-semibold text-ink">Import Tasks</h2>
                <button
                    type="button"
                    @click="importOpen = false"
                    class="-m-1.5 rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                    aria-label="Close import"
                >
                    <x-icon name="x" class="size-4" />
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.tasks.import') }}"
                enctype="multipart/form-data"
                class="p-6"
                @submit="importBusy = true"
            >
                @csrf

                <label for="import-file" class="block text-[13px] font-medium text-ink-soft">Upload Excel File</label>

                <label
                    for="import-file"
                    class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-line-strong bg-surface-sunken px-6 py-8 text-center transition-colors hover:border-accent hover:bg-surface-hover"
                >
                    <x-icon name="document" class="size-6 text-ink-faint" />
                    <span class="text-[13px] font-medium text-ink">
                        <span x-text="importBusy ? 'Importing...' : 'Choose a file or drop it here'"></span>
                    </span>
                    <span class="text-xs text-ink-faint">Supported format: .xlsx · max 10 MB</span>
                </label>

                <input
                    id="import-file"
                    name="file"
                    type="file"
                    accept=".xlsx"
                    required
                    class="sr-only"
                    x-ref="file"
                    @change="fileName = $event.target.files[0]?.name ?? ''"
                >

                <p class="mt-2 flex items-center justify-between gap-3 text-xs">
                    <span class="truncate font-medium" :class="fileName ? 'text-ink-soft' : 'text-ink-faint'" x-text="fileName || 'No file selected'"></span>
                    <span class="shrink-0 text-ink-faint">Valid status: progress, done, canceled</span>
                </p>

                <x-field-error name="file" />

                <div class="mt-6 flex items-center justify-between gap-3">
                    <a
                        href="{{ route('admin.tasks.import.template') }}"
                        class="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-soft underline-offset-4 transition-colors hover:text-ink hover:underline"
                    >
                        <x-icon name="download" class="size-3.5" />
                        Download Template
                    </a>

                    <div class="flex gap-2.5">
                        <x-button variant="secondary" @click="importOpen = false" x-bind:disabled="importBusy">
                            Cancel
                        </x-button>
                        <x-button type="submit" x-bind:disabled="importBusy">
                            <span x-show="!importBusy">Import</span>
                            <span x-cloak x-show="importBusy">Importing...</span>
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>