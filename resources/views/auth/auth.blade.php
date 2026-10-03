@extends('layouts.guest')

@section('title', 'Sign in')

{{--
    Isi panel form untuk halaman auth MEeLData.

    Posisi panel (brand kiri/kanan, form kiri/kanan) diatur oleh layout
    `guest.blade.php` yang mendeklarasikan satu `x-data` di atas kedua
    panel. Karena Alpine mencari ekspresi ke scope leluhur, view ini cukup
    mereferensikan `isRegister` tanpa membuat scope baru (kalau dibuat,
    state-nya akanbayangan/override state layout).
--}}
@section('content')
<div>
    {{-- Sign in --}}
    <div
        x-show="! isRegister"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    >
        <x-auth-card mode="login" />
    </div>

    {{-- Create account --}}
    <div
        x-cloak
        x-show="isRegister"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    >
        <x-auth-card mode="register" />
    </div>
</div>
@endsection