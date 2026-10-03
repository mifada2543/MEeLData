<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Masuk') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-canvas font-sans text-ink antialiased">
{{--
    Halaman auth MEeLData — satu gateway untuk admin & trainee.

    Dua panel setengah lebar yang BERTUKAR POSISI saat mode berpindah:

        mode Sign in                 mode Create account
        ┌───────────┬───────────┐    ┌───────────┬───────────┐
        │  BRAND    │  FORM     │    │  FORM     │  BRAND    │
        │  (kiri)   │  (kanan)  │    │  (kiri)   │  (kanan)  │
        └───────────┴───────────┘    └───────────┴───────────┘

    Kedua panel bergerak berlawanan arah secara bersamaan, sehingga terasa
    seperti saling menumpuk. Perpindahan memakai `transform` satu panel
    penuh (bukan kartu kecil), jadi:
      - tetap menutup layar penuh (tidak ada celah saat berpindah),
      - brand tetap full-height & full-bleed,
      - isi di dalam panel tidak ikut "balik".

    Di bawah `lg` keduanya menjadi layout biasa (brand disembunyikan) dan
    hanya terjadi cross-fade tanpa gerak horizontal.
--}}
<div
    x-data="{
        mode: '{{ $mode === 'register' ? 'register' : 'login' }}',
        get isRegister() { return this.mode === 'register' },
        setMode(next) {
            if (this.mode === next) return
            this.mode = next
            window.history.replaceState({}, '', next === 'register'
                ? @js(route('register'))
                : @js(route('login')))
        },
    }"
    class="auth-stage"
>
    {{-- Panel brand MEeLData --}}
    <div
        class="auth-panel auth-brand bg-accent"
        :class="isRegister ? 'auth-brand--right' : 'auth-brand--left'"
    >
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true">
            <div
                class="h-full w-full"
                style="background-image:linear-gradient(to right,white 1px,transparent 1px),linear-gradient(to bottom,white 1px,transparent 1px);background-size:56px 56px;"
            ></div>
        </div>

        <div class="relative flex h-full flex-col justify-between p-10 xl:p-14">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white/10 text-sm font-semibold tracking-tight text-white">ML</span>
                <span class="flex flex-col leading-tight">
                    <span class="text-[15px] font-semibold text-white">MEeLData</span>
                    <span class="text-[11px] text-white/45">Pengelolaan Tugas Trainee</span>
                </span>
            </div>

            <div class="max-w-md">
                <h2 class="text-[30px] font-semibold leading-[1.12] tracking-tight text-white">
                    Semua tugas trainee<br>dalam satu tempat.
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-white/60">
                    Catat, berikan, pantau, dan ekspor tugas trainee tanpa spread&nbsp;sheet.
                </p>

                <ul class="mt-9 space-y-3.5 text-sm text-white/70">
                    @foreach ([
                        ['clock', 'Status otomatis berubah menjadi Terlambat saat lewat deadline'],
                        ['shield', 'Setiap trainee hanya dapat melihat dan mengekspor tugasnya sendiri'],
                        ['document', 'Impor & ekspor Excel dengan laporan kegagalan per baris'],
                    ] as [$icon, $label])
                        <li class="flex items-start gap-2.5">
                            <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-white/40" />
                            <span>{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-white/30">
                &copy; {{ now()->year }} MEeLData
            </p>
        </div>
    </div>

    {{-- Panel form (login / register) --}}
    <div
        class="auth-panel auth-form"
        :class="isRegister ? 'auth-form--left' : 'auth-form--right'"
    >
        <div class="w-full max-w-sm">
            {{-- Identitas MEeLData untuk layar kecil (panel brand disembunyikan di bawah lg) --}}
            <div class="mb-7 flex items-center gap-2.5 lg:hidden">
                <span class="flex size-9 items-center justify-center rounded-[10px] bg-accent text-[13px] font-semibold text-white">ML</span>
                <span class="flex flex-col leading-tight">
                    <span class="text-sm font-semibold text-ink">MEeLData</span>
                    <span class="text-[11px] text-ink-faint">Pengelolaan Tugas Trainee</span>
                </span>
            </div>

            @yield('content')
        </div>
    </div>

    {{-- Diumumkan ke screen reader saat mode berganti --}}
    <p class="sr-only" aria-live="polite">
        <span x-text="isRegister ? 'Menampilkan formulir pendaftaran akun' : 'Menampilkan formulir masuk'"></span>
    </p>
</div>
</body>
</html>