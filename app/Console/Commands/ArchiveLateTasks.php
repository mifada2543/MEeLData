<?php

namespace App\Console\Commands;

use App\Models\TraineeTask;
use Illuminate\Console\Command;

/**
 * Auto-arsip task yang sudah terlambat.
 *
 * Berbeda dari `tasks:mark-late` (yang menandai `late` setiap menit),
 * perintah ini menghapus task `late` yang sudah melewati masa tenggang
 * agar daftar aktif tidak dipenuhi task basi.
 *
 * Masa tenggang diukur dari `deadline`, bukan dari kapan task ditandai
 * `late`, dan dapat diatur lewat `config('meeldata.late_archive_grace_days')`
 * atau variabel `LATE_ARCHIVE_GRACE_DAYS`.
 *
 * Idempotent: `SoftDeletes` secara default mengecualikan task yang sudah
 * terhapus, sehingga menjalankan perintah dua kali tidak berpengaruh.
 */
class ArchiveLateTasks extends Command
{
    protected $signature = 'tasks:archive-late';

    protected $description = 'Soft-delete late tasks that passed the archive grace period';

    public function handle(): int
    {
        $grace = max(0, (int) config('meeldata.late_archive_grace_days', 7));
        $cutoff = now()->subDays($grace);

        $archived = 0;

        TraineeTask::query()
            ->statusLate()
            ->where('deadline', '<', $cutoff)
            ->eachById(function (TraineeTask $task) use (&$archived): void {
                // `deleted_by` dibiarkan null: task dihapus oleh sistem,
                // bukan oleh admin atau trainee.
                $task->delete();
                $archived++;
            });

        $this->info("{$archived} late task(s) archived (deadline < {$cutoff->toDateTimeString()}, grace {$grace} hari).");

        if ($archived > 0) {
            $this->line('Tugas terarsip tetap dapat dilihat dan dipulihkan admin lewat menu Sampah.');
        }

        return self::SUCCESS;
    }
}
