<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Exports\TasksExport;
use App\Exports\TaskTemplateExport;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\ImportTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Imports\TasksImport;
use App\Models\Admin;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TraineeTaskController extends Controller
{
    /**
     * Halaman daftar task. Query yang sama dipakai oleh export agar
     * hasil export konsisten dengan filter yang sedang aktif.
     */
    public function index(Request $request): View
    {
        $status = $this->validateStatusFilter($request);

        $tasks = $this->visibleTasks($request->user())
            ->statusIs($status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('tasks.create', [
            'trainees' => $this->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : null,
            'task' => new TraineeTask,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        TraineeTask::create($request->taskAttributes());

        return redirect()
            ->route($this->routePrefix().'tasks.index')
            ->with('status', 'Task created successfully.');
    }

    public function edit(string $task): View
    {
        $task = $this->findVisibleTask($task);

        return view('tasks.edit', [
            'task' => $task,
            'trainees' => $this->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : null,
        ]);
    }

    public function update(UpdateTaskRequest $request, string $task): RedirectResponse
    {
        $task = $this->findVisibleTask($task);

        $task->update($request->taskAttributes());

        return redirect()
            ->route($this->routePrefix().'tasks.index')
            ->with('status', 'Task updated successfully.');
    }

    /**
     * Inline status update. Status `late` ditolak oleh form request.
     */
    public function status(UpdateStatusRequest $request, string $task): RedirectResponse
    {
        $task = $this->findVisibleTask($task);

        $task->update(['status' => $request->validated('status')]);

        return back()->with('status', 'Task updated successfully.');
    }

    /**
     * Individual delete (soft delete) — hanya admin.
     */
    public function destroy(string $task): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        $this->findVisibleTask($task)->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('status', 'Task deleted successfully.');
    }

    /**
     * Bulk delete (soft delete) — hanya admin.
     */
    public function bulkDelete(BulkDeleteRequest $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        $ids = $request->validated('ids');

        $deleted = TraineeTask::visibleTo($request->user())
            ->whereKey($ids)
            ->get()
            ->each->delete()
            ->count();

        return redirect()
            ->route('admin.tasks.index')
            ->with('status', trans_choice(
                '{1} :count task deleted successfully.|[2,*] :count tasks deleted successfully.',
                $deleted,
                ['count' => $deleted],
            ));
    }

    /**
     * Export memakai query yang sama dengan halaman list: scope ownership
     * + filter status aktif. Tidak pernah mengambil seluruh tabel.
     */
    public function export(Request $request)
    {
        $status = $this->validateStatusFilter($request);

        $filename = 'trainee-tasks-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new TasksExport($this->visibleTasks($request->user())->statusIs($status)),
            $filename
        );
    }

    /**
     * Import Excel (partial success). Row invalid dilaporkan per nomor baris.
     */
    public function import(ImportTasksRequest $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        $import = new TasksImport;

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('import_errors', [])
                ->with('import_failed', 'Import failed. The file must be a valid .xlsx Excel file.');
        }

        return back()->with([
            'imported_count' => $import->imported(),
            'import_errors' => $import->errors(),
        ]);
    }

    public function importTemplate()
    {
        abort_unless($this->isAdmin(), 403);

        return Excel::download(new TaskTemplateExport, 'trainee-task-import-template.xlsx');
    }

    /**
     * Base query untuk list & export. Admin melihat semua, trainee
     * hanya task miliknya sendiri (server-side data isolation).
     */
    protected function visibleTasks(?object $actor): Builder
    {
        return TraineeTask::query()->visibleTo($actor);
    }

    /**
     * Resolusi task dengan authorization scope. Task milik orang lain
     * menghasilkan 403; task yang tidak ada atau sudah soft-deleted
     * menghasilkan 404.
     */
    protected function findVisibleTask(string $uuid): TraineeTask
    {
        $task = $this->visibleTasks(Auth::user())->whereKey($uuid)->first();

        if ($task !== null) {
            return $task;
        }

        // Soft-deleted task tidak tersedia pada active-task endpoint (404).
        // Task milik aktor lain yang masih aktif Forbidden (403).
        abort($this->isAdmin() ? 404 : 403);
    }

    protected function validateStatusFilter(Request $request): ?string
    {
        $status = $request->query('status');

        return in_array($status, TaskStatus::values(), true) ? $status : null;
    }

    protected function isAdmin(): bool
    {
        return Auth::user() instanceof Admin;
    }

    /**
     * Prefix route name sesuai guard aktif.
     */
    protected function routePrefix(): string
    {
        return $this->isAdmin() ? 'admin.' : '';
    }
}
