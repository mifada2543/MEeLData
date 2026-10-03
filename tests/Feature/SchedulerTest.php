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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => now()->subDays(4)->format('Y-m-d H:i:s'),
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Late->value,
        ]);
    }

    public function test_overdue_done_task_stays_done(): void
    {
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Done,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
        ]);
    }

    public function test_overdue_canceled_task_stays_canceled(): void
    {
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Canceled,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Canceled->value,
        ]);
    }

    public function test_task_with_future_deadline_is_not_touched(): void
    {
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_existing_late_task_is_not_reprocessed(): void
    {
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $task = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
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
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $boundary = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => now()->subDays(4)->format('Y-m-d H:i:s'),
            'deadline' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->artisan('tasks:mark-late')->assertSuccessful();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $boundary->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_approaching_deadline_scope_uses_three_day_window(): void
    {
        Carbon::setTestNow(now()->addDays(9)->format('Y-m-d H:i:s'));

        $admin = $this->admin();

        $withinWindow = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'start_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDay()->format('Y-m-d H:i:s'), // +1 hari
        ]);

        $outsideWindow = TraineeTask::factory()->create([
            'status' => TaskStatus::Progress,
            'deadline' => now()->addDays(10)->format('Y-m-d H:i:s'), // +10 hari
        ]);

        $doneExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Done,
            'deadline' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $canceledExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Canceled,
            'deadline' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $lateExcluded = TraineeTask::factory()->create([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'), // sudah lewat 1 hari
        ]);

        $results = TraineeTask::visibleTo($admin)->approachingDeadline()->pluck('id');

        $this->assertTrue($results->contains($withinWindow->id));
        $this->assertFalse($results->contains($outsideWindow->id));
        $this->assertFalse($results->contains($doneExcluded->id));
        $this->assertFalse($results->contains($canceledExcluded->id));
        $this->assertFalse($results->contains($lateExcluded->id));
    }
}
