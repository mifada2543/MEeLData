@props(['mode'])   {{-- 'login' | 'register' --}}

{{--
    Kartu auth untuk kedua mode.

    Kedua mode memakai shell yang sama, tapi sengaja dibuat BERBEDA agar
    mudah dikenali:

        Sign in          -> eyebrow "Akses Trainee & Admin", tombol netral
        Create account   -> eyebrow "Untuk Trainee", tombol beraksen warna

    Field form tetap memakai id berawalan mode agar tidak bentrok dan
    asosiasi <label for> tetap benar.
--}}
@php
    $isRegister = $mode === 'register';
    $uid = $mode;

    $eyebrowIcon = $isRegister ? 'user' : 'shield';
    $eyebrowText = $isRegister ? 'Untuk trainee' : 'Akses trainee & admin';

    $title = $isRegister ? 'Buat akun Anda' : 'Selamat datang kembali';
    $subtitle = $isRegister
        ? 'Daftar sebagai trainee untuk mulai menerima tugas.'
        : 'Masuk untuk melanjutkan ke tugas Anda.';

    $submitLabel = $isRegister ? 'Buat akun saya' : 'Masuk';
@endphp

<div class="rounded-xl border border-line bg-surface p-6 shadow-sm sm:p-7">
    {{-- Eyebrow: penanda cepat mode apa yang sedang dibuka --}}
    <p class="mb-5 inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-sunken px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-muted">
        <x-icon :name="$eyebrowIcon" class="size-3.5" />
        {{ $eyebrowText }}
    </p>

    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        <p class="mt-1.5 text-sm text-ink-muted">{{ $subtitle }}</p>
    </div>

    <x-toast />

    @if ($isRegister)
        {{-- Register --}}
        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="{{ $uid }}_name" class="block text-[13px] font-medium text-ink-soft">Nama lengkap</label>
                <input
                    id="{{ $uid }}_name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    required
                    autocomplete="name"
                    placeholder="Nama lengkap Anda"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="name" />
            </div>

            <div>
                <label for="{{ $uid }}_email" class="block text-[13px] font-medium text-ink-soft">Email</label>
                <input
                    id="{{ $uid }}_email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="username"
                    placeholder="anda@contoh.com"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="email" />
            </div>

            <div>
                <label for="{{ $uid }}_password" class="block text-[13px] font-medium text-ink-soft">Kata sandi</label>
                <input
                    id="{{ $uid }}_password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="password" />
            </div>

            <div>
                <label for="{{ $uid }}_password_confirmation" class="block text-[13px] font-medium text-ink-soft">Ulangi kata sandi</label>
                <input
                    id="{{ $uid }}_password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Ketik ulang kata sandi"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="password_confirmation" />
            </div>

            <x-button type="submit" variant="alt" class="w-full">
                {{ $submitLabel }}
                <x-icon name="arrow-right" class="size-4" />
            </x-button>
        </form>

        <div class="mt-6 border-t border-line pt-5 text-center text-[13px] text-ink-muted">
            Sudah punya akun?
            <button
                type="button"
                @click="setMode('login')"
                class="font-medium text-ink underline underline-offset-4 hover:no-underline focus-visible:outline-none"
            >
                Masuk
            </button>
        </div>
    @else
        {{-- Login --}}
        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="{{ $uid }}_email" class="block text-[13px] font-medium text-ink-soft">Email</label>
                <input
                    id="{{ $uid }}_email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="username"
                    placeholder="anda@contoh.com"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="email" />
            </div>

            <div>
                <label for="{{ $uid }}_password" class="block text-[13px] font-medium text-ink-soft">Kata sandi</label>
                <input
                    id="{{ $uid }}_password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
                >
                <x-field-error name="password" />
            </div>

            <label for="{{ $uid }}_remember" class="flex cursor-pointer items-center gap-2 text-[13px] text-ink-soft">
                <input
                    id="{{ $uid }}_remember"
                    name="remember"
                    type="checkbox"
                    value="1"
                    class="size-4 rounded border-line-strong accent-[var(--color-accent)]"
                >
                Ingat saya
            </label>

            <x-button type="submit" class="w-full">
                {{ $submitLabel }}
                <x-icon name="arrow-right" class="size-4" />
            </x-button>
        </form>

        <div class="mt-6 border-t border-line pt-5 text-center text-[13px] text-ink-muted">
            Belum punya akun?
            <button
                type="button"
                @click="setMode('register')"
                class="font-medium text-ink underline underline-offset-4 hover:no-underline focus-visible:outline-none"
            >
                Daftar
            </button>
        </div>
    @endif
</div>