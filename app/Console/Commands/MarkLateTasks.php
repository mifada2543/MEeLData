<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Console\Command;

/**
 * Menyelaraskan status `late` dengan deadline yang tersimpan.
 *
 * Dua aturan yang saling melengkapi:
 *
 *   1. progress & deadline < now - 120s  ->  late
 *      Jendelanya 120 detik (`late_mark_after_seconds`) supaya counter
 *      "dalam M:SS" sempat terlihat sebelum task ditandai terlambat.
 *
 *   2. late     & deadline >= now         ->  progress
 *      `late` berarti "deadline lewat dan belum selesai". Begitu deadline
 *      digeser ke masa depan - lewat edit, restore, impor, atau bahkan ubah
 *      langsung di database - premisen itu gugur, jadi statusnya wajib
 *      dikoreksi. Tanpa aturan ini, satu task yang deadline-nya sudah
 *      di-perbaiki akan tersangkut "Terlambat" selamanya.
 *
 * Kedua batas itu saling melengkapi (tidak tumpang tindih), sehingga tidak
 * ada kondisi yang bisa memicu flip-flop bolak-balik dalam satu menit.
 *
 * Idempotent: `done` dan `canceled` tidak pernah disentuh.
 */
class MarkLateTasks extends Command
{
    protected $signature = 'tasks:mark-late';

    protected $description = 'Sync derived late status with each task deadline';

    public function handle(): int
    {
        $marked = TraineeTask::query()
            ->overdueProgress()
            ->update(['status' => TaskStatus::Late->value]);

        // Deadline masih di masa depan: premis "terlambat" sudah gugur.
        $restored = TraineeTask::query()
            ->lateWithFutureDeadline()
            ->update(['status' => TaskStatus::Progress->value]);

        $this->info("{$marked} task(s) marked as late, {$restored} returned to progress.");

        return self::SUCCESS;
    }
}
