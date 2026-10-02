<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overdue_progress_task_becomes_late(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => '2026-10-01 09:00:00',
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_overdue_done_task_stays_done(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Done,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
        ]);
    }

    public function test_overdue_canceled_task_stays_canceled(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Canceled,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Canceled->value,
        ]);
    }

    public function test_task_with_future_deadline_is_not_touched(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => '2026-10-05 09:00:00',
            'deadline' => '2026-10-11 17:00:00',
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_existing_late_task_is_not_reprocessed(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $updatedAt = $task->updated_at;

        $this->artisan('tasks:mark-late')->assertSuccessful();
        $this->artisan('tasks:mark-late')->assertSuccessful();

        // Idempotent: updated_at tidak berubah karena tidak ada update.
        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);

        $this->assertTrue($task->fresh()->updated_at->equalTo($updatedAt));
    }

    public function test_command_is_idempotent_across_multiple_runs(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $this->artisan('tasks:mark-late');
        $this->artisan('tasks:mark-late');
        $this->artisan('tasks:mark-late');

        $this->assertSame(
            TaskStatus::Late,
            TraineeTask::find($task->id)->status,
        );
    }

    public function test_soft_deleted_overdue_task_is_not_resurrected(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => '2026-10-09 17:00:00',
        ]);
        $task->delete();

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertSoftDeleted('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_manual_late_recovery_to_progress_is_reverted_by_scheduler(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        // Admin mengembalikan status late -> progress secara manual.
        $task->update(['status' => TaskStatus::Progress->value]);
        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);

        // Scheduler berikutnya akan menandainya late lagi.
        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_manual_late_recovery_to_done_is_not_reverted(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $task->update(['status' => TaskStatus::Done->value]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
        ]);
    }

    public function test_scheduler_is_registered_every_minute(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'tasks:mark-late'))
            ->all();

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
    }

    public function test_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_deadline_boundary_uses_strictly_less_than_now(): void
    {
        // Deadline tepat sama dengan "sekarang" belum dianggap overdue.
        Carbon::setTestNow('2026-10-10 12:00:00');

        $boundary = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => '2026-10-01 09:00:00',
            'deadline' => '2026-10-10 12:00:00',
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $boundary->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_approaching_deadline_scope_uses_three_day_window(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $admin = $this->admin();

        $withinWindow = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => '2026-10-08 09:00:00',
            'deadline' => '2026-10-11 17:00:00', // +1 hari
        ]);

        $outsideWindow = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => '2026-10-20 17:00:00', // +10 hari
        ]);

        $doneExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Done,
            'deadline' => '2026-10-11 17:00:00',
        ]);

        $canceledExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Canceled,
            'deadline' => '2026-10-11 17:00:00',
        ]);

        $lateExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => '2026-10-09 17:00:00',
        ]);

        $results = TraineeTask::visibleTo($admin)->approachingDeadline()->pluck('id');

        $this->assertTrue($results->contains($withinWindow->id));
        $this->assertFalse($results->contains($outsideWindow->id));
        $this->assertFalse($results->contains($doneExcluded->id));
        $this->assertFalse($results->contains($canceledExcluded->id));
        $this->assertFalse($results->contains($lateExcluded->id));
    }
}
