@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
    $prefix = $isAdmin ? 'admin.' : '';
@endphp

{{--
    Filter modal (design.md §12). MVP hanya filter status.
    Export memakai query yang sama dengan list sehingga hasilnya konsisten.
--}}
<div x-data="{ filterOpen: false }">
    <x-button variant="secondary" @click="filterOpen = true">
        <x-icon name="filter" class="size-4" />
        Filter
        @if ($status)
            <span class="rounded-full bg-accent-soft px-1.5 py-0.5 text-[11px] font-semibold text-ink">
                {{ \App\Enums\TaskStatus::from($status)->label() }}
            </span>
        @endif
    </x-button>

    <div
        x-cloak
        x-show="filterOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="filter-title"
    >
        <div
            x-show="filterOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]"
            @click="filterOpen = false"
            aria-hidden="true"
        ></div>

        <div
            x-show="filterOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-1 scale-[0.99]"
            x-trap.noscroll="filterOpen"
            @keydown.escape.window="filterOpen = false"
            class="relative w-full max-w-md overflow-hidden rounded-xl border border-line bg-surface shadow-lg"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 id="filter-title" class="text-[15px] font-semibold text-ink">Filter Tasks</h2>
                <button
                    type="button"
                    @click="filterOpen = false"
                    class="-m-1.5 rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                    aria-label="Close filter"
                >
                    <x-icon name="x" class="size-4" />
                </button>
            </div>

            <form method="GET" action="{{ route($prefix.'tasks.index') }}" class="p-6">
                <label for="filter-status" class="block text-[13px] font-medium text-ink-soft">Status</label>
                <div class="relative mt-1.5">
                    <select
                        id="filter-status"
                        name="status"
                        class="h-9.5 w-full appearance-none rounded-md border border-line-strong bg-surface pl-3 pr-9 text-sm text-ink transition-colors outline-none hover:bg-surface-hover focus-visible:border-accent"
                    >
                        <option value="">All Statuses</option>
                        @foreach (\App\Enums\TaskStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected($status === $case->value)>
                                {{ $case->label() }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-ink-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <div class="mt-6 flex justify-end gap-2.5">
                    <x-button variant="secondary" :href="route($prefix.'tasks.index')">Reset</x-button>
                    <x-button type="submit">Apply</x-button>
                </div>
            </form>

            {{-- Export memakai filter status yang sama dengan list. --}}
            <div class="border-t border-line bg-surface-sunken px-6 py-4">
                <p class="section-label mb-2.5">Export</p>
                <x-button
                    variant="secondary"
                    class="w-full"
                    :href="route($prefix.'tasks.export', $status ? ['status' => $status] : [])"
                >
                    <x-icon name="download" class="size-4" />
                    Export Result
                    @if ($status)
                        <span class="text-ink-faint">· {{ \App\Enums\TaskStatus::from($status)->label() }}</span>
                    @endif
                </x-button>
            </div>
        </div>
    </div>
</div>