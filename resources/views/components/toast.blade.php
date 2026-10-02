@php
    $statusMessage = session('status');
    $failedMessage = session('import_failed');
@endphp

{{--
    Toast (design.md §22): feedback setelah setiap action berhasil.
    Muncul di kanan atas, auto-dismiss, dapat ditutup manual, dan
    diumumkan ke screen reader lewat role="status".
--}}
<div
    x-data="{ toasts: [] }"
    @keydown.escape.window="toasts = []"
    class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
>
    @if ($statusMessage)
        <div
            x-data="{ shown: true }"
            x-show="shown"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-init="setTimeout(() => shown = false, 4500)"
            role="status"
            aria-live="polite"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-done-border bg-surface p-3.5 shadow-popover"
        >
            <x-icon name="check-circle" class="mt-px size-[18px] shrink-0 text-done" />
            <p class="flex-1 text-[13px] leading-relaxed text-ink">{{ $statusMessage }}</p>
            <button
                type="button"
                @click="shown = false"
                class="-m-1 shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                aria-label="Dismiss notification"
            >
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    @endif

    @if ($failedMessage)
        <div
            x-data="{ shown: true }"
            x-show="shown"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-init="setTimeout(() => shown = false, 6000)"
            role="alert"
            aria-live="assertive"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-late-border bg-surface p-3.5 shadow-popover"
        >
            <x-icon name="alert" class="mt-px size-[18px] shrink-0 text-late" />
            <p class="flex-1 text-[13px] leading-relaxed text-ink">{{ $failedMessage }}</p>
            <button
                type="button"
                @click="shown = false"
                class="-m-1 shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-surface-hover hover:text-ink"
                aria-label="Dismiss notification"
            >
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    @endif
</div>