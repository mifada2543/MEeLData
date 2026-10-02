@extends('layouts.app')

@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
    $prefix = $isAdmin ? 'admin.' : '';
@endphp

@section('title', 'Create Task')
@section('heading', 'Create Task')
@section('subheading', 'Assign a new task to a trainee')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-line bg-surface shadow-xs">
        <div class="border-b border-line px-6 py-4">
            <h2 class="text-[15px] font-semibold text-ink">Task details</h2>
            <p class="mt-0.5 text-[13px] text-ink-muted">All fields marked required must be filled.</p>
        </div>

        <div class="p-6">
            @include('tasks.partials.form', [
                'task' => $task,
                'trainees' => $trainees,
                'isAdmin' => $isAdmin,
                'action' => route($prefix.'tasks.store'),
                'submitLabel' => 'Submit',
                'formId' => 'task-form',
            ])
        </div>
    </div>
</div>
@endsection