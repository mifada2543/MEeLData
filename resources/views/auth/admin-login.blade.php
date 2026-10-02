@extends('layouts.guest')

@section('title', 'Admin Sign In')
@section('subheading', 'Manage all trainee tasks')

@section('content')
    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-5">
        @csrf

        <div class="flex items-center gap-2.5 rounded-lg border border-line bg-surface-sunken px-3.5 py-2.5">
            <x-icon name="shield" class="size-4 shrink-0 text-ink-faint" />
            <p class="text-[12.5px] leading-relaxed text-ink-muted">
                Admin accounts are provisioned by seeder only. There is no admin registration.
            </p>
        </div>

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink-soft">Admin email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="admin@example.com"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="email" />
        </div>

        <div>
            <label for="password" class="block text-[13px] font-medium text-ink-soft">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="password" />
        </div>

        <x-button type="submit" class="w-full">
            Sign in as Admin
            <x-icon name="arrow-right" class="size-4" />
        </x-button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-ink underline underline-offset-4 hover:no-underline">Trainee sign in</a>
@endsection