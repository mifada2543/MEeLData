@props([
    'variant' => 'primary',   // primary | alt | secondary | ghost | danger | dangerSoft
    'size' => 'md',           // sm | md
    'href' => null,
    'type' => 'button',
])

{{-- Tombol dengan satu style konsisten untuk semua aksi (design.md §2.4). --}}
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium whitespace-nowrap transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-55';

    $sizes = [
        'sm' => 'h-8 px-3 text-[13px]',
        'md' => 'h-9.5 px-3.5 text-sm',
    ];

    $variants = [
        'primary' => 'bg-accent text-white hover:bg-accent-hover',
        // Aksen sekunder, khusus halaman auth (card Create account).
        'alt' => 'bg-accent-alt text-white hover:bg-accent-alt-hover',
        'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-surface-hover',
        'ghost' => 'text-ink-soft hover:bg-surface-hover hover:text-ink',
        'danger' => 'bg-late text-white hover:opacity-90',
        'dangerSoft' => 'border border-late-border bg-late-surface text-late hover:bg-late-surface/70',
    ];

    $classes = trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif