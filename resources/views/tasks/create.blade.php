@extends('layouts.app')

@use('Illuminate\Support\Facades\Auth')
@use('App\Models\User')

@php
    $isAdmin = User::isAdminActor(Auth::user());
    $prefix = $isAdmin ? 'admin.' : '';
@endphp

@section('title', 'Tambah Tugas')
@section('heading', 'Tambah Tugas')
@section('subheading', 'Tugaskan tugas baru kepada trainee')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-line bg-surface shadow-xs">
        <div class="border-b border-line px-6 py-4">
            <h2 class="text-[15px] font-semibold text-ink">Detail tugas</h2>
            <p class="mt-0.5 text-[13px] text-ink-muted">Semua kolom bertanda wajib harus diisi.</p>
        </div>

        <div class="p-6">
            @include('tasks.partials.form', [
                'task' => $task,
                'trainees' => $trainees,
                'isAdmin' => $isAdmin,
                'prefix' => $prefix,
                'action' => route($prefix.'tasks.store'),
                'submitLabel' => 'Simpan',
                'formId' => 'task-form',
            ])
        </div>
    </div>
</div>
@endsection