<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Auth') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-canvas font-sans text-ink antialiased">
<div class="grid min-h-dvh lg:grid-cols-2">

    {{-- Brand panel: hanya di layar besar --}}
    <div class="relative hidden overflow-hidden bg-accent p-12 lg:flex lg:flex-col lg:justify-between">
        {{-- Dekorasi halus: grid tipis, tanpa gradient ramai --}}
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true">
            <div class="h-full w-full"
                 style="background-image:linear-gradient(to right,white 1px,transparent 1px),linear-gradient(to bottom,white 1px,transparent 1px);background-size:56px 56px;"></div>
        </div>

        <div class="relative flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-[10px] bg-white/10 text-sm font-semibold text-white">TT</span>
            <span class="text-sm font-semibold text-white">Trainee Tasks</span>
        </div>

        <div class="relative max-w-md">
            <h2 class="text-[28px] font-semibold leading-[1.15] tracking-tight text-white">
                {{ config('app.name') }}
            </h2>
            <p class="mt-3 text-[15px] leading-relaxed text-white/60">
                Satu tempat terpusat untuk mencatat, memberikan, memantau, dan mengekspor tugas trainee.
            </p>

            <ul class="mt-8 space-y-3 text-sm text-white/70">
                @foreach ([
                    ['check-circle', 'Status tugas otomatis diperbarui saat deadline terlewati'],
                    ['shield', 'Setiap trainee hanya melihat tugas miliknya sendiri'],
                    ['document', 'Import dan export Excel dengan laporan baris per baris'],
                ] as [$icon, $label])
                    <li class="flex items-start gap-2.5">
                        <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-white/45" />
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-xs text-white/35">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </p>
    </div>

    {{-- Form panel --}}
    <div class="flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-16">
        <div class="mx-auto w-full max-w-sm">

            <div class="mb-8 flex items-center gap-2.5 lg:hidden">
                <span class="flex size-9 items-center justify-center rounded-[10px] bg-accent text-sm font-semibold text-white">TT</span>
                <span class="text-sm font-semibold text-ink">Trainee Tasks</span>
            </div>

            <x-toast />

            <div class="mb-7">
                <h1 class="text-xl font-semibold tracking-tight text-ink">@yield('title', 'Sign in')</h1>
                <p class="mt-1.5 text-sm text-ink-muted">@yield('subheading')</p>
            </div>

            @yield('content')

            @hasSection('footer')
                <div class="mt-7 border-t border-line pt-5 text-[13px] text-ink-muted">
                    @yield('footer')
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>