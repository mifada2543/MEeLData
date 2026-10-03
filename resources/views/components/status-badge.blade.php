@props(['status', 'size' => 'md'])

{{--
    Status sebagai chip berwarna + label teks.
    Warna tidak pernah menjadi satu-satunya indikator: label selalu
    ditampilkan (design.md §3.2 & §8).
--}}
@php
    [$text, $surface, $border, $dot] = $status->badgeClasses();

    $sizes = [
        'sm' => 'gap-1.5 px-2 py-0.5 text-[11px]',
        'md' => 'gap-1.5 px-2.5 py-1 text-xs',
    ];

    $dotSizes = [
        'sm' => 'size-1.5',
        'md' => 'size-1.5',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => "inline-flex items-center rounded-full border font-medium $sizes[$size] $text $surface $border",
    ]) }}
>
    <span class="size-1.5 shrink-0 rounded-full {{ $dot }} {{ $dotSizes[$size] }}" aria-hidden="true"></span>
    {{ $status->label() }}
</span>