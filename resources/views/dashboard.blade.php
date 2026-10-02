@extends('layouts.app')

@php
    $isAdmin = auth()->user() instanceof \App\Models\Admin;
@endphp

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', $isAdmin ? 'Overview of all trainee tasks' : 'Overview of your assigned tasks')

@section('content')
    {{--
        Stat row above, lists beneath. Empat metrik sudah berada di rentang
        yang direkomendasikan (5–7 KPI tiles), jadi tidak ada chart: data
        di sini adalah tabel/list, bukan grafik.
    --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Tasks', 'value' => $stats['total'], 'icon' => 'inbox', 'accent' => 'text-ink'],
            ['label' => 'Progress', 'value' => $stats['progress'], 'icon' => 'clock', 'accent' => 'text-progress'],
            ['label' => 'Done', 'value' => $stats['done'], 'icon' => 'check-circle', 'accent' => 'text-done'],
            ['label' => 'Late', 'value' => $stats['late'], 'icon' => 'alert', 'accent' => 'text-late'],
        ] as $stat)
            <div class="rounded-xl border border-line bg-surface p-4 shadow-xs">
                <div class="flex items-start justify-between gap-2">
                    <p class="section-label">{{ $stat['label'] }}</p>
                    <x-icon :name="$stat['icon']" class="size-4 shrink-0 text-ink-faint" />
                </div>
                <p class="mt-2 text-[28px] font-semibold leading-none tracking-tight tabular-nums text-ink">
                    {{ $stat['value'] }}
                </p>
            </div>
        @endforeach
    </div>

    @if ($stats['total'] > 0)
        {{-- Progress bar: representasi komposisi tanpa chart. --}}
        <div class="mt-3 overflow-hidden rounded-xl border border-line bg-surface px-5 py-4 shadow-xs">
            <div class="flex items-center justify-between gap-4">
                <p class="section-label">Status distribution</p>
                <p class="text-[11px] tabular-nums text-ink-faint">
                    {{ $stats['done'] }} done · {{ $stats['progress'] }} in progress
                    @if ($stats['late'] > 0) · {{ $stats['late'] }} late @endif
                </p>
            </div>

            @php
                $segments = [
                    ['value' => $stats['done'], 'class' => 'bg-done'],
                    ['value' => $stats['progress'], 'class' => 'bg-progress'],
                    ['value' => $stats['late'], 'class' => 'bg-late'],
                    ['value' => $stats['canceled'], 'class' => 'bg-canceled'],
                ];
                $total = max($stats['total'], 1);
            @endphp

            <div class="mt-2.5 flex h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken">
                @foreach ($segments as $segment)
                    @if ($segment['value'] > 0)
                        <div
                            class="{{ $segment['class'] }} h-full first:rounded-l-full last:rounded-r-full"
                            style="width: {{ round($segment['value'] / $total * 100, 2) }}%"
                            role="presentation"
                        ></div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        @include('dashboard.partials.task-list', [
            'heading' => 'Recent Tasks',
            'tasks' => $recentTasks,
            'isAdmin' => $isAdmin,
            'icon' => 'list',
            'emptyMessage' => 'No tasks yet.',
        ])

        @include('dashboard.partials.task-list', [
            'heading' => 'Approaching Deadline',
            'tasks' => $approachingTasks,
            'isAdmin' => $isAdmin,
            'icon' => 'clock',
            'emptyMessage' => 'Nothing due in the next 3 days.',
        ])

        @include('dashboard.partials.task-list', [
            'heading' => 'Late Tasks',
            'tasks' => $lateTasks,
            'isAdmin' => $isAdmin,
            'icon' => 'alert',
            'emptyMessage' => 'No late tasks. Nice work.',
        ])
    </div>
@endsection