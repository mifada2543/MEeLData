<?php

namespace App\Http\Controllers;

use App\Models\TraineeTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint Ajax untuk tampilan "live" pada daftar tugas.
 *
 * Yang dikembalikan:
 * - `server_time` : acuan waktu server. Client menghitung offset terhadap
 *   jam perangkat lalu men-tick dengan offset itu, sehingga hitungan
 *   "sudah lewat 1 menit 12 detik" tetap benar walau jam perangkat salah
 *   DAN tidak melompat/reset saat halaman di-refresh.
 * - `overdue`     : task yang masih `progress` padahal deadline lewat
 *   (kelompok 4). Inilah sumber data hitungan mundur.
 * - `scheduler_down_count` : task yang lewat deadline LEBIH dari jendela
 *   penandaan dan masih `progress` - satu-satunya kondisi yang benar-benar
 *   berarti "scheduler tidak jalan".
 *
 * Query memakai scope `visibleTo()` yang sama dengan halaman list, jadi
 * trainee hanya pernah menerima task miliknya sendiri.
 */
class TaskLiveController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $scope = fn () => TraineeTask::query()->visibleTo($request->user());

        $overdue = ($scope)()
            ->awaitingScheduler()
            ->get(['id', 'deadline'])
            ->map(fn (TraineeTask $task) => [
                'id' => $task->id,
                'deadline' => $task->deadline->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'mark_after_seconds' => TraineeTask::lateMarkAfterSeconds(),
            'poll_seconds' => max(5, (int) config('meeldata.live_poll_seconds', 10)),
            'scheduler_down_count' => ($scope)()->overdueProgress()->count(),
            'overdue' => $overdue,
        ])->header('Cache-Control', 'no-store, private');
    }
}
