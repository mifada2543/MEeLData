<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Tasks') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-canvas font-sans text-ink antialiased">
@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
    $prefix = $isAdmin ? 'admin.' : '';
    $tasksRoute = $prefix.'tasks.index';
@endphp

<div x-data="{ sidebarOpen: false }" class="min-h-full">
    {{-- Overlay backdrop untuk navigation drawer di layar kecil --}}
    <div
        x-cloak
        x-show="sidebarOpen"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-30 bg-ink/20 backdrop-blur-[2px] lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-[248px] flex-col border-r border-line bg-surface transition-transform duration-200 ease-out lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0 shadow-lg' : '-translate-x-full'"
    >
        {{-- Brand --}}
        <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
            <a href="{{ route($prefix.'dashboard') }}" class="flex items-center gap-2.5 rounded-md focus-visible:outline-none">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-[10px] bg-accent text-[13px] font-semibold tracking-tight text-white">TT</span>
                <span class="flex flex-col leading-tight">
                    <span class="text-[13px] font-semibold text-ink">Trainee Tasks</span>
                    <span class="text-[11px] text-ink-faint">{{ $isAdmin ? 'Admin Console' : 'My Workspace' }}</span>
                </span>
            </a>

            <button
                type="button"
                class="-mr-1.5 rounded-md p-1.5 text-ink-muted hover:bg-surface-hover hover:text-ink focus-visible:outline-none lg:hidden"
                @click="sidebarOpen = false"
                aria-label="Close navigation"
            >
                <x-icon name="x" />
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2" aria-label="Main navigation">
            <p class="section-label px-3 pb-2 pt-3">Workspace</p>

            <a
                href="{{ route($prefix.'dashboard') }}"
                @class([
                    'group flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    'bg-accent-soft text-ink' => request()->routeIs($prefix.'dashboard'),
                    'text-ink-soft hover:bg-surface-hover hover:text-ink' => ! request()->routeIs($prefix.'dashboard'),
                ])
                @if (request()->routeIs($prefix.'dashboard')) aria-current="page" @endif
            >
                <x-icon name="grid" class="size-[18px] shrink-0 {{ request()->routeIs($prefix.'dashboard') ? 'text-ink' : 'text-ink-faint group-hover:text-ink-soft' }}" />
                Dashboard
            </a>

            <a
                href="{{ route($tasksRoute) }}"
                @class([
                    'group flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    'bg-accent-soft text-ink' => request()->routeIs($prefix.'tasks.*'),
                    'text-ink-soft hover:bg-surface-hover hover:text-ink' => ! request()->routeIs($prefix.'tasks.*'),
                ])
                @if (request()->routeIs($prefix.'tasks.*')) aria-current="page" @endif
            >
                <x-icon name="list" class="size-[18px] shrink-0 {{ request()->routeIs($prefix.'tasks.*') ? 'text-ink' : 'text-ink-faint group-hover:text-ink-soft' }}" />
                {{ $isAdmin ? 'Tasks' : 'My Tasks' }}
            </a>

            {{-- Footer area in sidebar --}}
            <div class="mt-auto pt-4">
                <div class="rounded-lg border border-line bg-surface-sunken p-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[12px] font-semibold text-ink">
                            {{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-ink">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11px] text-ink-faint">{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route($isAdmin ? 'admin.logout' : 'logout') }}" class="mt-2.5">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-[13px] font-medium text-ink-muted transition-colors hover:bg-surface hover:text-ink focus-visible:outline-none"
                        >
                            <x-icon name="logout" class="size-4" />
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </nav>
    </aside>

    {{-- Main --}}
    <div class="lg:pl-[248px]">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-canvas/85 px-4 backdrop-blur-md sm:px-6 lg:px-8">
            <button
                type="button"
                class="-ml-1.5 rounded-md p-2 text-ink-muted transition-colors hover:bg-surface-hover hover:text-ink focus-visible:outline-none lg:hidden"
                @click="sidebarOpen = true"
                aria-label="Open navigation"
            >
                <x-icon name="menu" />
            </button>

            <div class="min-w-0 flex-1">
                {{--
                    Top bar sengaja tidak mengulang judul halaman (judul sudah
                    ada sebagai H1 di konten). Yang ditampilkan adalah konteks
                    waktu lokal, karena deadline task memakai timezone aplikasi.
                --}}
                <p class="flex items-center gap-1.5 truncate text-[13px] text-ink-muted">
                    <x-icon name="clock" class="size-3.5 shrink-0 text-ink-faint" />
                    <span class="font-medium text-ink-soft">{{ now()->format('d M Y') }}</span>
                    <span class="hidden sm:inline">
                        <span class="px-1 text-ink-faint">&middot;</span>
                        {{ now()->format('H:i') }} {{ config('app.timezone') }}
                    </span>
                </p>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] font-medium text-ink-muted">
                <x-icon :name="$isAdmin ? 'shield' : 'user'" class="size-3.5" />
                {{ $isAdmin ? 'Admin' : 'Trainee' }}
            </span>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-toast />

            {{-- Page heading: satu verdict per halaman (prinsip "one verdict per screen") --}}
            <div class="mb-6">
                <h1 class="text-[22px] font-semibold tracking-tight text-ink">
                    @hasSection('heading')
                        @yield('heading')
                    @else
                        @yield('title', 'Dashboard')
                    @endif
                </h1>

                @hasSection('subheading')
                    <p class="mt-1 text-sm text-ink-muted">@yield('subheading')</p>
                @endif
            </div>

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>