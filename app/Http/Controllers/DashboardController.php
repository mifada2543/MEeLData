<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // Dashboard trainee hanya memakai task miliknya sendiri.
        $scope = TraineeTask::query()->visibleTo($request->user());

        $counts = (clone $scope)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => $counts->sum(),
            'progress' => (int) ($counts[TaskStatus::Progress->value] ?? 0),
            'done' => (int) ($counts[TaskStatus::Done->value] ?? 0),
            'late' => (int) ($counts[TaskStatus::Late->value] ?? 0),
            'canceled' => (int) ($counts[TaskStatus::Canceled->value] ?? 0),
        ];

        return view('dashboard', [
            'stats' => $stats,
            'recentTasks' => (clone $scope)->with('trainee:id,name')->latest()->take(5)->get(),
            'approachingTasks' => (clone $scope)
                ->with('trainee:id,name')
                ->approachingDeadline()
                ->orderBy('deadline')
                ->take(5)
                ->get(),
            'lateTasks' => (clone $scope)
                ->with('trainee:id,name')
                ->statusLate()
                ->orderBy('deadline')
                ->take(5)
                ->get(),
        ]);
    }
}
