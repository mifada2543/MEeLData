@extends('layouts.guest')

@section('title', 'Sign in')
@section('subheading', 'Access your assigned trainee tasks')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink-soft">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="you@example.com"
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

        <label for="remember" class="flex cursor-pointer items-center gap-2 text-[13px] text-ink-soft">
            <input
                id="remember"
                name="remember"
                type="checkbox"
                value="1"
                class="size-4 rounded border-line-strong accent-[var(--color-accent)]"
            >
            Keep me signed in
        </label>

        <x-button type="submit" class="w-full">
            Sign in
            <x-icon name="arrow-right" class="size-4" />
        </x-button>
    </form>
@endsection

@section('footer')
    New here?
    <a href="{{ route('register') }}" class="font-medium text-ink underline underline-offset-4 hover:no-underline">Create an account</a>
    <span class="px-1.5 text-ink-faint">&middot;</span>
    <a href="{{ route('admin.login') }}" class="text-ink-muted underline underline-offset-4 hover:text-ink hover:no-underline">Admin sign in</a>
@endsection