@extends('layouts.app')

@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
    $prefix = $isAdmin ? 'admin.' : '';
    $total = $tasks->total();
@endphp

@section('title', $isAdmin ? 'Tasks' : 'My Tasks')
@section('heading', $isAdmin ? 'Tasks' : 'My Tasks')
@section('subheading', $total.' '.Str::plural('task', $total).' · '.($isAdmin ? 'all trainees' : 'assigned to you'))

@section('content')
<div
    x-data="{
        selected: [],
        busy: false,
        toggleAll(checked) {
            this.selected = checked
                ? Array.from(document.querySelectorAll('.bulk-checkbox')).map((el) => el.value)
                : []
        },
    }"
>
    {{-- Action bar: import di kiri, aksi utama di kanan --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @if ($isAdmin)
                @include('tasks.partials.filter-modal')
                @include('tasks.partials.import-modal')
            @else
                @include('tasks.partials.filter-modal')
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($isAdmin)
                @include('tasks.partials.bulk-delete')
            @endif

            <x-button :href="route($prefix.'tasks.create')">
                <x-icon name="plus" class="size-4" />
                Add Task
            </x-button>
        </div>
    </div>

    {{-- Import result (partial success reporting) --}}
    @if (session('imported_count') !== null || count(session('import_errors', [])) > 0)
        @include('tasks.partials.import-result')
    @endif

    {{-- Table: surface utama aplikasi (prinsip "table-first") --}}
    <div class="overflow-hidden rounded-xl border border-line bg-surface shadow-xs">
        @if ($tasks->isEmpty())
            @if ($status)
                <x-empty-state
                    icon="filter"
                    title="No tasks match the selected filter"
                    message="Nothing here for this status. Try another status or reset the filter."
                    action-label="Add Task"
                    :action-href="route($prefix.'tasks.create')"
                />
            @else
                <x-empty-state
                    title="No tasks found"
                    message="There are currently no tasks. Create the first one to get started."
                    action-label="Add Task"
                    :action-href="route($prefix.'tasks.create')"
                />
            @endif
        @else
            {{-- Petunjuk scroll: hanya tampil di layar kecil (design.md §20). --}}
            <p class="flex items-center gap-1.5 border-b border-line bg-surface-sunken px-4 py-2 text-[11.5px] text-ink-muted lg:hidden">
                <svg class="size-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M12.79 14.77a.75.75 0 0 0 .02 1.06l4.5 4.25a.75.75 0 1 0 1.08-1.04l-4.5-4.25a.75.75 0 0 0-1.1.02Z" clip-rule="evenodd" />
                </svg>
                Swipe horizontally to see all columns
            </p>

            <div class="overflow-x-auto">
                {{--
                    min-width memicu horizontal scroll di layar kecil
                    (design.md §20). Deskripsi disembunyikan di bawah `sm`
                    karena bersifat sekunder dan menyulitkan pemindaian.
                --}}
                <table class="w-full border-collapse text-sm {{ $isAdmin ? 'min-w-[1000px]' : 'min-w-[680px]' }}">
                    <thead>
                        <tr class="border-b border-line">
                            @if ($isAdmin)
                                <th scope="col" class="table-header-cell w-11">
                                    <input
                                        type="checkbox"
                                        @change="toggleAll($event.target.checked)"
                                        class="size-4 rounded border-line-strong text-accent accent-[var(--color-accent)] focus-visible:outline-none"
                                        aria-label="Select all tasks on this page"
                                    >
                                </th>
                            @endif

                            @if ($isAdmin)
                                <th scope="col" class="table-header-cell w-48">Trainee</th>
                            @endif

                            <th scope="col" class="table-header-cell">Task</th>
                            <th scope="col" class="table-header-cell w-36">Start</th>
                            <th scope="col" class="table-header-cell w-36">Deadline</th>
                            <th scope="col" class="table-header-cell w-40">Status</th>
                            <th scope="col" class="table-header-cell w-28 text-right">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        @foreach ($tasks as $task)
                            <tr class="group/row transition-colors hover:bg-surface-hover/60">
                                @if ($isAdmin)
                                    <td class="table-body-cell w-11">
                                        <input
                                            type="checkbox"
                                            name="ids[]"
                                            value="{{ $task->id }}"
                                            form="bulk-delete-form"
                                            x-model="selected"
                                            class="bulk-checkbox size-4 rounded border-line-strong accent-[var(--color-accent)] focus-visible:outline-none"
                                            aria-label="Select task {{ $task->task }}"
                                        >
                                    </td>
                                    <td class="table-body-cell w-48">
                                        <span class="font-medium text-ink">{{ $task->trainee?->name ?? '—' }}</span>
                                    </td>
                                @endif

                                <td class="table-body-cell">
                                    <p class="font-medium text-ink">{{ $task->task }}</p>
                                    @if ($task->desc)
                                        <p class="mt-0.5 hidden max-w-sm truncate text-[12.5px] text-ink-muted sm:block">{{ $task->desc }}</p>
                                    @endif
                                </td>

                                <td class="table-body-cell w-36 whitespace-nowrap text-ink-muted">
                                    {{ $task->start_date->format('d M Y') }}
                                    <span class="block text-[12px] text-ink-faint">{{ $task->start_date->format('H:i') }}</span>
                                </td>

                                <td class="table-body-cell w-36 whitespace-nowrap">
                                    <span class="text-ink-soft">{{ $task->deadline->format('d M Y') }}</span>
                                    <span class="block text-[12px] text-ink-faint">{{ $task->deadline->format('H:i') }}</span>

                                    @if ($task->isAwaitingLateMarking())
                                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-late">
                                            <x-icon name="clock" class="size-3" />
                                            overdue
                                        </span>
                                    @endif
                                </td>

                                <td class="table-body-cell w-40">
                                    <x-status-badge :status="$task->status" />
                                    <x-status-select :task="$task" :route-prefix="$prefix" />
                                </td>

                                <td class="table-body-cell w-28">
                                    <div class="flex items-center justify-end gap-1">
                                        <a
                                            href="{{ route($prefix.'tasks.edit', ['task' => $task->id]) }}"
                                            class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline-none"
                                            title="Edit task"
                                        >
                                            <x-icon name="pencil" class="size-4" />
                                            <span class="sr-only">Edit {{ $task->task }}</span>
                                        </a>

                                        @if ($isAdmin)
                                            @include('tasks.partials.delete-button', ['task' => $task])
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tasks->hasPages())
                <div class="border-t border-line px-4 py-3">
                    {{ $tasks->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection