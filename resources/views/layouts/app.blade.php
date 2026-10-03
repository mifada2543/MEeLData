@use('Illuminate\Support\Facades\Auth')
@use('App\Models\User')
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
    /*
     * `Auth::user()` dipakai, bukan `auth()->user()`: helper `auth()`
     * mengembalikan AuthManager yang method-nya diteruskan lewat `__call`,
     * sehingga analis statis tidak bisa melihat `user()`. Facade `Auth`
     * mendeklarasikannya secara eksplisit. Guard `web` memakai
     * `App\Models\User` (config/auth.php), jadi `User::isAdminActor()`
     * aman dipakai di sini.
     */
    $user = Auth::user();
    $isAdmin = User::isAdminActor($user);
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
                <span class="flex size-8 shrink-0 items-center justify-center rounded-[10px] bg-accent text-[13px] font-semibold tracking-tight text-white">ML</span>
                <span class="flex flex-col leading-tight">
                    <span class="text-[13px] font-semibold text-ink">MEeLData</span>
                    <span class="text-[11px] text-ink-faint">Pengelolaan Tugas Trainee</span>
                </span>
            </a>

            <button
                type="button"
                class="-mr-1.5 rounded-md p-1.5 text-ink-muted hover:bg-surface-hover hover:text-ink focus-visible:outline-none lg:hidden"
                @click="sidebarOpen = false"
                aria-label="Tutup navigasi"
            >
                <x-icon name="x" />
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2" aria-label="Main navigation">
            <p class="section-label px-3 pb-2 pt-3">Ruang kerja</p>

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
                {{ $isAdmin ? 'Tugas' : 'Tugas Saya' }}
            </a>

            @if ($isAdmin)
                {{--
                    Halaman Sampah hanya untuk admin, sehingga menu ini juga
                    hanya muncul di panel admin.
                --}}
                <a
                    href="{{ route('admin.tasks.trash') }}"
                    @class([
                        'group flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        'bg-accent-soft text-ink' => request()->routeIs('admin.tasks.trash', 'admin.tasks.restore.*'),
                        'text-ink-soft hover:bg-surface-hover hover:text-ink' => ! request()->routeIs('admin.tasks.trash', 'admin.tasks.restore.*'),
                    ])
                    @if (request()->routeIs('admin.tasks.trash', 'admin.tasks.restore.*')) aria-current="page" @endif
                >
                    <x-icon name="trash" class="size-[18px] shrink-0 {{ request()->routeIs('admin.tasks.trash', 'admin.tasks.restore.*') ? 'text-ink' : 'text-ink-faint group-hover:text-ink-soft' }}" />
                    Sampah
                </a>
            @endif
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
                aria-label="Buka navigasi"
            >
                <x-icon name="menu" />
            </button>

            <div class="min-w-0 flex-1">
                {{--
                    Top bar sengaja tidak mengulang judul halaman (judul sudah
                    ada sebagai H1 di konten). Yang ditampilkan adalah jam
                    aplikasi, karena deadline task memakai timezone aplikasi.
                --}}
                <x-clock />
            </div>

            {{--
                Kartu profil + tombol Keluar dipindahkan ke top bar agar
                sidebar hanya berisi navigasi.
            --}}
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[12px] font-semibold text-ink">
                        {{ Str::upper(Str::substr($user->name, 0, 2)) }}
                    </span>

                    <div class="hidden min-w-0 sm:block">
                        <p class="truncate text-[13px] font-semibold leading-tight text-ink">
                            {{ $user->name }}
                        </p>
                        <p class="flex items-center gap-1 truncate text-[11px] leading-tight text-ink-faint">
                            <x-icon :name="$isAdmin ? 'shield' : 'user'" class="size-3 shrink-0" />
                            <span class="truncate">{{ $user->email }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>{{ $isAdmin ? 'Admin' : 'Trainee' }}</span>
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button variant="secondary" size="sm" type="submit" class="px-2.5">
                        <x-icon name="logout" class="size-4 shrink-0" />
                        <span class="hidden md:inline">Keluar</span>
                        <span class="sr-only md:hidden">Keluar</span>
                    </x-button>
                </form>
            </div>
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