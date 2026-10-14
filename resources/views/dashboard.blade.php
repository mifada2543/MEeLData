@extends('layouts.app')

@use('Illuminate\Support\Facades\Auth')
@use('App\Models\User')

@php
$isAdmin = User::isAdminActor(Auth::user());
$prefix = $isAdmin ? 'admin.' : '';

/*
* Setiap KPI dan daftar dashboard ditautkan ke halaman daftar dengan
* filter yang sesuai. Tujuan: task terlambat harus bisa ditemukan dari
* mana pun, bukan hanya dengan menggulir halaman daftar.
*/
$tasksRoute = route($prefix.'tasks.index');

$statusUrl = fn (?string $status) => $status
? route($prefix.'tasks.index', ['status' => $status])
: $tasksRoute;
@endphp

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', $isAdmin ? 'Ringkasan seluruh tugas trainee' : 'Ringkasan tugas yang ditugaskan kepada Anda')

@section('content')
{{--
        Stat row above, lists beneath. Empat metrik sudah berada di rentang
        yang direkomendasikan (5–7 KPI tiles), jadi tidak ada chart: data
        di sini adalah tabel/list, bukan grafik.
    --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
    ['label' => 'Total Tugas', 'value' => $stats['total'], 'icon' => 'inbox', 'status' => null],
    ['label' => 'Progress', 'value' => $stats['progress'], 'icon' => 'clock', 'status' => 'progress'],
    ['label' => 'Selesai', 'value' => $stats['done'], 'icon' => 'check-circle', 'status' => 'done'],
    ['label' => 'Terlambat', 'value' => $stats['late'], 'icon' => 'alert', 'status' => 'late'],
    ] as $stat)
    {{-- KPI sebagai tautan: langsung ke daftar dengan filter yang sama. --}}
    <a
        href="{{ $statusUrl($stat['status']) }}"
        class="rounded-xl border border-line bg-surface p-4 shadow-xs transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
        <div class="flex items-start justify-between gap-2">
            <p class="section-label">{{ $stat['label'] }}</p>
            <x-icon :name="$stat['icon']" class="size-4 shrink-0 text-ink-faint" />
        </div>
        <p class="mt-2 text-[28px] font-semibold leading-none tracking-tight tabular-nums text-ink">
            {{ $stat['value'] }}
        </p>
    </a>
    @endforeach
</div>

@if ($stats['total'] > 0)
{{-- Progress bar: representasi komposisi tanpa chart. --}}
<div class="mt-3 overflow-hidden rounded-xl border border-line bg-surface px-5 py-4 shadow-xs">
    <div class="flex items-center justify-between gap-4">
        <p class="section-label">Sebaran status</p>
        <p class="text-[11px] tabular-nums text-ink-faint">
            {{ $stats['done'] }} selesai · {{ $stats['progress'] }} berjalan
            @if ($stats['late'] > 0) · {{ $stats['late'] }} terlambat @endif
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
        @php $pct = round(($segment['value'] / $total) * 100, 2) . '%'; @endphp
        <div
            class="{{ $segment['class'] }} h-full first:rounded-l-full last:rounded-r-full"
            style="width: {{ $pct }}"
            role="presentation"></div>
        @endif
        @endforeach
    </div>
</div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    @include('dashboard.partials.task-list', [
    'heading' => 'Tugas Terbaru',
    'tasks' => $recentTasks,
    // Halaman daftar selalu menampilkan seluruh task aktif -
    // apa pun statusnya, termasuk yang terlambat.
    'total' => $stats['total'],
    'filterUrl' => $tasksRoute,
    'isAdmin' => $isAdmin,
    'icon' => 'list',
    'emptyMessage' => 'Belum ada tugas.',
    ])

    @include('dashboard.partials.task-list', [
    'heading' => 'Mendekati Deadline',
    'tasks' => $approachingTasks,
    'total' => $approachingCount,
    // Tidak ada filter "mendekati deadline" di halaman daftar, jadi
    // tautannya ke daftar progress tanpa filter status lain.
    'filterUrl' => $statusUrl('progress'),
    'isAdmin' => $isAdmin,
    'icon' => 'clock',
    'emptyMessage' => 'Tidak ada yang jatuh tempo dalam 3 hari.',
    ])

    @include('dashboard.partials.task-list', [
    'heading' => 'Tugas Terlambat',
    'tasks' => $lateTasks,
    'total' => $lateCount,
    'filterUrl' => $statusUrl('late'),
    'isAdmin' => $isAdmin,
    'icon' => 'alert',
    'emptyMessage' => 'Tidak ada tugas terlambat. Bagus.',
    ])
</div>
@endsection