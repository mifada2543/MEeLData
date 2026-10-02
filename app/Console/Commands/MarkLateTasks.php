<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Console\Command;

/**
 * Menandai task yang deadline-nya sudah lewat dan masih berstatus
 * progress menjadi late. Idempotent: task yang sudah late tidak
 * tersentuh, dan `done`/`canceled` tidak pernah diubah.
 */
class MarkLateTasks extends Command
{
    protected $signature = 'tasks:mark-late';

    protected $description = 'Mark overdue progress tasks as late';

    public function handle(): int
    {
        $affected = TraineeTask::query()
            ->overdueProgress()
            ->update(['status' => TaskStatus::Late->value]);

        $this->info("{$affected} task(s) marked as late.");

        return self::SUCCESS;
    }
}
