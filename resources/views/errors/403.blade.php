<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Akses Ditolak &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-canvas px-4 font-sans text-ink antialiased">
    <div class="w-full max-w-sm text-center">
        <div class="mx-auto flex size-11 items-center justify-center rounded-xl border border-late-border bg-late-surface text-late">
            <x-icon name="shield" class="size-5" />
        </div>

        <p class="mt-5 text-[13px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Error 403</p>
        <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-ink">Akses Ditolak</h1>
        <p class="mt-2 text-sm leading-relaxed text-ink-muted">
            Anda tidak memiliki izin untuk mengakses sumber daya ini.
        </p>

        <x-button :href="url('/')" class="mt-7">
            Kembali
        </x-button>
    </div>
</body>
</html>