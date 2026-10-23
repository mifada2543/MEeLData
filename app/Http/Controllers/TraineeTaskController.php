<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Exports\TasksExport;
use App\Exports\TaskTemplateExport;
use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\ExportTasksRequest;
use App\Http\Requests\ImportTasksRequest;
use App\Http\Requests\RestoreTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Imports\TasksImport;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TraineeTaskController extends Controller
{
    /**
     * Halaman daftar task. Query yang sama dipakai oleh export agar hasil
     * export konsisten dengan filter dan urutan yang sedang aktif.
     */
    public function index(Request $request): View
    {
        $status = $this->validateStatusFilter($request);

        $scoped = fn () => $this->visibleTasks($request->user())->statusIs($status);

        $overdueIds = $scoped()->awaitingScheduler()->pluck('id')->all();

        $tasks = $scoped()
            ->byWorkPriority()
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'status' => $status,
            // `lateCount` = sudah ditandai scheduler.
            // `pendingLateCount` = seluruh progress yang lewat deadline
            //   (isi kelompok 4, sumber hitungan mundur).
            // `schedulerDownCount` = yang lewat MELAMPIUI jendela penandaan
            //   dan masih progress - satu-satunya kondisi yang benar-benar
            //   berarti 'scheduler tidak jalan'. Kalau ini memakai
            //   `pendingLateCount`, setiap task yang baru lewat deadline akan
            //   memicu peringatan palsu selama jendela 120 detik.
            'lateCount' => $scoped()->statusLate()->count(),
            // Jumlah per status untuk baris filter. Tanpa ini, urutan
            // prioritas membuat task terlambat terdorong ke halaman
            // terakhir sehingga praktis tidak terlihat lagi.
            'statusCounts' => $this->statusCounts($scoped()),
            'pendingLateCount' => $scoped()->awaitingScheduler()->count(),
            'schedulerDownCount' => $scoped()->overdueProgress()->count(),
            'archiveGraceDays' => $this->archiveGraceDays(),
            // Acuan waktu untuk counter, supaya benar sejak frame pertama.
            'serverNowIso' => now()->toIso8601String(),
            // Daftar id yang sedang menunggu penandaan. Disuntik ke store
            // saat render supaya ticker tahu task mana yang "pernah overdue"
            // - tanpa itu, task berdeadline depan ikut berubah jadi badge
            // Terlambat begitu polling pertama selesai.
            'overdueIds' => $overdueIds,
        ]);
    }

    /**
     * Halaman Sampah: task yang sudah soft-delete. Hanya admin.
     *
     * Memakai `trashed()` (onlyTrashed) karena `findVisibleTask()` tidak
     * pernah menemukan task terhapus - global scope SoftDeletes
     * mengecualikannya.
     */
    public function trashed(): View
    {
        abort_unless($this->isAdmin(), 403);

        $tasks = TraineeTask::query()
            ->trashed()
            ->with(['trainee:id,name', 'deletedBy:id,name'])
            ->latest('deleted_at')
            ->paginate(25);

        return view('tasks.trash', [
            'tasks' => $tasks,
            'archiveGraceDays' => $this->archiveGraceDays(),
            // Tanpa polling di halaman ini: yang dibutuhkan hanya acuan
            // waktu server untuk menghitung "terlambat 2 hari 3 jam".
            'serverNowIso' => now()->toIso8601String(),
        ]);
    }

    /**
     * Form pemulihan task: ganti tanggal, lalu pulihkan.
     */
    public function restoreForm(string $task): View
    {
        abort_unless($this->isAdmin(), 403);

        $model = TraineeTask::query()->trashed()->findOrFail($task);

        return view('tasks.restore', [
            'task' => $model,
            // Tanggal usul sengaja di masa depan supaya form langsung valid.
            // Deadline lama tidak dipakai karena nilainya sudah lewat.
            'suggestedStart' => now(),
            'suggestedDeadline' => $model->deadline->gt(now())
                ? $model->deadline
                : now()->addDays($this->archiveGraceDays()),
        ]);
    }

    /**
     * Pulihkan task yang sudah soft-delete, dengan tanggal baru.
     */
    public function restore(RestoreTaskRequest $request, string $task): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        TraineeTask::query()
            ->trashed()
            ->findOrFail($task)
            ->restoreWithDates($request->taskDates());

        return redirect()
            ->route('admin.tasks.index')
            ->with('status', 'Tugas berhasil dipulihkan.');
    }

    public function create(): View
    {
        return view('tasks.create', [
            'trainees' => $this->assignableTrainees(),
            'task' => new TraineeTask,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        TraineeTask::create($request->taskAttributes());

        return redirect()
            ->route($this->routePrefix().'tasks.index')
            ->with('status', 'Tugas berhasil dibuat.');
    }

    public function edit(string $task): View
    {
        $task = $this->findVisibleTask($task);

        $this->authorizeEdit($task);

        return view('tasks.edit', [
            'task' => $task,
            'trainees' => $this->assignableTrainees(),
        ]);
    }

    public function update(UpdateTaskRequest $request, string $task): RedirectResponse
    {
        $task = $this->findVisibleTask($task);

        $this->authorizeEdit($task);

        $task->update($request->taskAttributes());

        // Deadline yang digeser ke masa depan membuat status `late` tidak
        // lagi benar. Dikoreksi di sini (bukan menunggu cron) supaya user
        // langsung melihat hasilnya; scheduler punya ruleset yang sama
        // sebagai pengaman untuk jalur lain.
        $task->reconcileDerivedStatus();

        return redirect()
            ->route($this->routePrefix().'tasks.index')
            ->with('status', 'Tugas berhasil diperbarui.');
    }

    /**
     * Menolak edit dari trainee atas task yang sudah final.
     *
     * Disembunyikan juga di UI (tombol Edit tidak dirender), tapi disembunyikan
     * bukan pengaman - aturan ini yang benar-benar menolak. Pesannya diambil
     * dari model supaya sebab dan tampilan selalu sama.
     */
    protected function authorizeEdit(TraineeTask $task): void
    {
        // Non-null karena rute ini berada di balik middleware `auth`.
        /** @var User $user */
        $user = Auth::user();

        if ($task->isEditableBy($user)) {
            return;
        }

        abort(403, $task->editLockReason() ?? 'Tugas ini tidak dapat diubah.');
    }

    /**
     * Inline status update. Status `late` ditolak oleh form request.
     */
    public function status(UpdateStatusRequest $request, string $task): RedirectResponse
    {
        $task = $this->findVisibleTask($task);

        $task->update(['status' => $request->validated('status')]);

        return back()->with('status', 'Tugas berhasil diperbarui.');
    }

    /**
     * Individual delete (soft delete) — hanya admin.
     */
    public function destroy(string $task): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        // `deleted_by` diisi manual delete; auto-arsip scheduler membiarkannya
        // null sehingga halaman Sampah bisa membedakan "oleh sistem".
        $model = $this->findVisibleTask($task);

        $model->forceFill(['deleted_by' => Auth::id()])->save();
        $model->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('status', 'Tugas berhasil dihapus.');
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
            ->each(function (TraineeTask $task) use ($request): void {
                $task->forceFill(['deleted_by' => $request->user()->getAuthIdentifier()])->save();
                $task->delete();
            })
            ->count();

        return redirect()
            ->route('admin.tasks.index')
            ->with('status', $deleted.' tugas berhasil dihapus.');
    }

    /**
     * Export memakai query yang sama dengan halaman list: scope ownership
     * + (id terpilih ATAU filter status aktif).
     *
     * - Bila ada baris dicentang, hanya baris itu yang diekspor.
     * - Bila tidak ada, seluruh hasil filter aktif yang diekspor.
     *
     * `ids` selalu diinterseksi dengan `visibleTo()`, sehingga trainee yang
     * mengirim UUID milik trainee lain hanya mendapatkan tasknya sendiri.
     */
    public function export(ExportTasksRequest $request)
    {
        $selectedIds = $request->selectedIds();
        $status = $this->validateStatusFilter($request);

        $isSelected = $selectedIds !== [];

        $filename = 'meeldata-tasks'.($isSelected ? '-selected' : '').'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new TasksExport($this->exportQuery($request->user(), $selectedIds, $status)),
            $filename
        );
    }

    /**
     * Membangun query export. Terpisah dari `export()` agar aturan
     * "selected menang" dapat diuji tanpa menghasilkan file.
     *
     * @param  array<int, string>  $selectedIds
     */
    public function exportQuery(?object $actor, array $selectedIds = [], ?string $status = null): Builder
    {
        $query = $this->visibleTasks($actor);

        // Selected menang: bila ada id dipilih, filter status diabaikan.
        $query = $selectedIds !== []
            ? $query->whereKey($selectedIds)
            : $query->statusIs($status);

        // Urutan sama dengan halaman list agar isi file mencerminkan layar.
        return $query->byWorkPriority();
    }

    /**
     * Import Excel (partial success). Row invalid dilaporkan per nomor baris.
     *
     * Admin wajib mengisi email trainee; trainee selalu mengimpor untuk
     * akunnya sendiri. Aturan ini ditentukan dari aktor yang login, bukan
     * dari isi file.
     */
    public function import(ImportTasksRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $import = new TasksImport($actor);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('import_errors', [])
                ->with('import_failed', 'Impor gagal. Berkas harus berupa file Excel .xlsx yang valid.');
        }

        $imported = $import->imported();
        $errors = $import->errors();

        $payload = [
            'imported_count' => $imported,
            'import_errors' => $errors,
        ];

        /*
         * Umpan balik langsung. Banner "Hasil Impor" memuat rincian per baris,
         * sedangkan toast memastikan pengguna langsung tahu bahwa ada baris
         * yang gagal - termasuk saat sebagian baris lain berhasil.
         */
        if ($errors !== []) {
            $payload['import_failed'] = $imported > 0
                ? "Impor selesai. {$imported} tugas ditambahkan, ".count($errors).' baris gagal. Periksa rincian di Hasil Impor.'
                : 'Tidak ada baris yang berhasil diimpor. '.count($errors).' baris gagal. Periksa rincian di Hasil Impor.';
        } elseif ($imported > 0) {
            $payload['status'] = "Impor berhasil. {$imported} tugas baru ditambahkan.";
        }

        return back()->with($payload);
    }

    public function importTemplate(Request $request)
    {
        return Excel::download(
            new TaskTemplateExport(User::isAdminActor($request->user())),
            'meeldata-task-import-template.xlsx'
        );
    }

    /**
     * Daftar trainee yang dapat dipilih admin. Akun admin sengaja tidak
     * ditampilkan walaupun berada pada tabel yang sama.
     */
    protected function assignableTrainees(): ?Collection
    {
        if (! $this->isAdmin()) {
            return null;
        }

        return User::query()
            ->where('role', User::ROLE_TRAINEE)
            ->orderBy('name')
            ->get(['id', 'name']);
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
        /*
         * Lihat catatan yang sama di routes/web.php: facade `Auth`
         * mendeklarasikan `user()` sebagai `Authenticatable|null`, padahal
         * guard `web` memakai `App\Models\User`.
         */
        /** @var User|null $user */
        $user = Auth::user();

        return (bool) $user?->isAdmin();
    }

    /**
     * Jumlah task per status untuk baris filter.
     *
     * Semua status selalu dikembalikan (termasuk yang nol) supaya urutannya
     * stabil dan tidak bergantung pada data.
     *
     * @param  Builder<TraineeTask>  $query
     * @return array<string, int>
     */
    protected function statusCounts(Builder $query): array
    {
        $counts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (TaskStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * Masa tenggang auto-arsip (hari), dari config.
     */
    protected function archiveGraceDays(): int
    {
        return max(0, (int) config('meeldata.late_archive_grace_days', 7));
    }

    /**
     * Prefix route name sesuai guard aktif.
     */
    protected function routePrefix(): string
    {
        return $this->isAdmin() ? 'admin.' : '';
    }
}
