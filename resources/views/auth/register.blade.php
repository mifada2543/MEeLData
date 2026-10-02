@extends('layouts.guest')

@section('title', 'Create account')
@section('subheading', 'Register as a trainee to receive tasks')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-[13px] font-medium text-ink-soft">Name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
                placeholder="Your full name"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="name" />
        </div>

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink-soft">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
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
                autocomplete="new-password"
                placeholder="At least 8 characters"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="password" />
        </div>

        <div>
            <label for="password_confirmation" class="block text-[13px] font-medium text-ink-soft">Confirm password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Repeat your password"
                class="mt-1.5 h-10 w-full rounded-md border border-line-strong bg-surface px-3 text-sm text-ink transition-colors outline-none placeholder:text-ink-faint hover:bg-surface-hover focus-visible:border-accent"
            >
            <x-field-error name="password_confirmation" />
        </div>

        <x-button type="submit" class="w-full">
            Create account
            <x-icon name="arrow-right" class="size-4" />
        </x-button>
    </form>
@endsection

@section('footer')
    Already registered?
    <a href="{{ route('login') }}" class="font-medium text-ink underline underline-offset-4 hover:no-underline">Sign in instead</a>
@endsection