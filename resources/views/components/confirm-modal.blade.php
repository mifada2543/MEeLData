@props([
    'title',
    'message' => null,
    'confirmLabel' => 'Confirm',
    'busyLabel' => null,
    'tone' => 'primary',
    'open' => 'confirmOpen',
    'formId',
])

{{--
    Confirmation dialog generik (design.md §22).

    Kontrak:
    - $open    nama variabel Alpine boolean yang harus SUDAH ada di scope
               induk (dideklarasikan oleh pemanggil lewat x-data).
    - $formId  id dari elemen <form> yang ada di halaman. Tombol konfirmasi
               memakai atribut HTML5 form="" sehingga boleh berada di luar
               form (mis. checkbox bulk delete berada di dalam tabel).
    - $busy    variabel Alpine boolean untuk mencegah submit ganda.
    - Slot $trigger  tombol pemicu (opsional).

    Komponen ini sengaja tidak membuat scope x-data sendiri agar `open` dan
    `busy` tetap menjadi satu sumber state dengan form pemanggil.
--}}
@php
    $confirmClasses = match ($tone) {
        'danger' => 'bg-late text-white hover:opacity-90',
        default => 'bg-accent text-white hover:bg-accent-hover',
    };
@endphp

@isset($trigger)
    {{ $trigger }}
@endisset

<div
    x-cloak
    x-show="{{ $open }}"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $formId }}-title"
>
    {{-- Backdrop --}}
    <div
        x-show="{{ $open }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]"
        @click="{{ $open }} = false"
        aria-hidden="true"
    ></div>

    {{-- Panel --}}
    <div
        x-show="{{ $open }}"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-[0.99]"
        x-trap.noscroll="{{ $open }}"
        @keydown.escape.window="{{ $open }} = false"
        class="relative w-full max-w-md rounded-xl border border-line bg-surface shadow-lg"
    >
        <div class="p-6">
            <div class="flex items-start gap-3.5">
                @if ($tone === 'danger')
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-late-surface text-late">
                        <x-icon name="alert" class="size-[18px]" />
                    </span>
                @endif

                <div class="min-w-0 flex-1">
                    <h2 id="{{ $formId }}-title" class="text-[15px] font-semibold text-ink">{{ $title }}</h2>

                    @if ($message)
                        <p class="mt-1.5 text-[13px] leading-relaxed text-ink-muted">{{ $message }}</p>
                    @endif

                    {{ $form ?? '' }}
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2.5 rounded-b-xl border-t border-line bg-surface-sunken px-6 py-4">
            <x-button variant="secondary" @click="{{ $open }} = false" x-bind:disabled="busy">
                Batal
            </x-button>

            <button
                type="submit"
                form="{{ $formId }}"
                class="inline-flex h-9.5 items-center justify-center gap-2 rounded-md px-3.5 text-sm font-medium text-white transition-colors disabled:cursor-not-allowed disabled:opacity-55 {{ $confirmClasses }}"
                x-bind:disabled="busy"
            >
                <span x-show="!busy">{{ $confirmLabel }}</span>
                <span x-cloak x-show="busy">{{ $busyLabel ?? $confirmLabel.'...' }}</span>
            </button>
        </div>
    </div>
</div>