@extends('layouts.app')

@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
    $prefix = $isAdmin ? 'admin.' : '';
@endphp

@section('title', 'Edit Task')
@section('heading', 'Edit Task')
@section('subheading', 'Update the details of this task')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-line bg-surface shadow-xs">
        <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
            <div class="min-w-0">
                <h2 class="truncate text-[15px] font-semibold text-ink">{{ $task->task }}</h2>
                <p class="mt-0.5 font-mono text-[11px] text-ink-faint">{{ $task->id }}</p>
            </div>
            <x-status-badge :status="$task->status" />
        </div>

        <div class="p-6">
            @include('tasks.partials.form', [
                'task' => $task,
                'trainees' => $trainees,
                'isAdmin' => $isAdmin,
                'action' => route($prefix.'tasks.update', ['task' => $task->id]),
                'submitLabel' => 'Save Changes',
                'formId' => 'task-form',
            ])
        </div>
    </div>
</div>
@endsection