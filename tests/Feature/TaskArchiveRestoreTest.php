<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Http\Controllers\TraineeTaskController;
use App\Models\TraineeTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Auto-arsip task terlambat (`tasks:archive-late`), halaman Sampah, dan
 * pemulihan task dengan penggantian tanggal.
 */
class TaskArchiveRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ----------------------------------------------------------- auto-arsip

    public function test_archives_late_task_past_grace_period(): void
    {
        Config::set('meeldata.late_archive_grace_days', 3);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDays(5), // lewat 5 hari > grace 3 hari
        ]);

        $this->artisan('tasks:archive-late')->assertSuccessful();

        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);

        // Dihapus oleh sistem => deleted_by null.
        $this->assertNull($task->fresh()->deleted_by);
        $this->assertNotNull($task->fresh()->deleted_at);
    }

    public function test_keeps_late_task_inside_grace_period(): void
    {
        Config::set('meeldata.late_archive_grace_days', 3);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task([
            'status' => TaskStatus::Late,
            'deadline' => now()->subDay(), // baru lewat 1 hari
        ]);

        $this->artisan('tasks:archive-late')->assertSuccessful();

        $this->assertNotSoftDeleted('trainee_tasks', ['id' => $task->id]);
    }

    public function test_only_late_tasks_are_archived(): void
    {
        Config::set('meeldata.late_archive_grace_days', 1);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $progress = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->subDays(9)]);
        $done = $this->task(['status' => TaskStatus::Done, 'deadline' => now()->subDays(9)]);
        $canceled = $this->task(['status' => TaskStatus::Canceled, 'deadline' => now()->subDays(9)]);

        $this->artisan('tasks:archive-late')->assertSuccessful();

        foreach ([$progress, $done, $canceled] as $task) {
            $this->assertNotSoftDeleted('trainee_tasks', ['id' => $task->id]);
        }
    }

    public function test_archive_is_idempotent(): void
    {
        Config::set('meeldata.late_archive_grace_days', 1);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(9)]);

        $this->artisan('tasks:archive-late')->assertSuccessful();
        $firstDeletedAt = $task->fresh()->deleted_at;

        $this->artisan('tasks:archive-late')->assertSuccessful();

        $this->assertEquals($firstDeletedAt, $task->fresh()->deleted_at);
        $this->assertSame(1, TraineeTask::onlyTrashed()->whereKey($task->id)->count());
    }

    public function test_zero_grace_archives_immediately(): void
    {
        Config::set('meeldata.late_archive_grace_days', 0);
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subSecond()]);

        $this->artisan('tasks:archive-late')->assertSuccessful();

        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);
    }

    public function test_archive_schedule_is_registered_daily(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'tasks:archive-late'));

        $this->assertCount(1, $events, 'tasks:archive-late harus terdaftar di scheduler.');

        // Cron untuk 00:05 = menit 5, jam 0.
        $this->assertSame('5 0 * * *', $events->first()->expression);
    }

    // -------------------------------------------------------------- halaman

    public function test_trash_page_lists_archived_tasks_with_source(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(5)]);
        $task->delete();

        $this->authAsAdmin()->get(route('admin.tasks.trash'))->assertOk()
            ->assertSee($task->task)
            ->assertSee('Terlambat')
            ->assertSee('otomatis oleh sistem')
            ->assertSee('Pulihkan');
    }

    public function test_trash_page_shows_who_deleted_manual_deletion(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $admin = $this->admin();
        $task = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $this->actingAs($admin, 'web')->delete(route('admin.tasks.destroy', $task));

        $this->assertNotNull($task->fresh()->deleted_at);

        $this->authAsAdmin()->get(route('admin.tasks.trash'))->assertOk()
            ->assertSee('oleh '.$admin->name);
    }

    public function test_trainee_cannot_open_trash_page(): void
    {
        $this->authAsTrainee()
            ->get(route('admin.tasks.trash'))
            ->assertForbidden();
    }

    public function test_trainee_cannot_open_restore_form(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);
        $task->delete();

        $this->authAsTrainee()
            ->get(route('admin.tasks.restore.form', ['task' => $task->id]))
            ->assertForbidden();
    }

    public function test_trainee_cannot_restore_task(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);
        $task->delete();

        $this->authAsTrainee()->post(route('admin.tasks.restore', ['task' => $task->id]), [
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);
    }

    public function test_archived_task_is_hidden_from_active_list_and_export(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(5)]);
        $task->delete();

        $admin = $this->admin();

        $this->assertFalse(TraineeTask::query()->visibleTo($admin)->whereKey($task->id)->exists());

        $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertDontSee($task->task);

        $this->assertNotContains(
            $task->id,
            app(TraineeTaskController::class)
                ->exportQuery($admin)
                ->pluck('id')
                ->all()
        );
    }

    // -------------------------------------------------------------- restore

    public function test_restore_uses_new_dates_and_resets_late_to_progress(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task([
            'status' => TaskStatus::Late,
            'start_date' => now()->subDays(10),
            'deadline' => now()->subDays(5),
        ]);
        $task->delete();

        $start = now()->addDay()->format('Y-m-d H:i:s');
        $deadline = now()->addDays(5)->format('Y-m-d H:i:s');

        $this->authAsAdmin()->post(route('admin.tasks.restore', ['task' => $task->id]), [
            'start_date' => $start,
            'deadline' => $deadline,
        ])->assertRedirect(route('admin.tasks.index'));

        $restored = $task->fresh();

        $this->assertNull($restored->deleted_at);
        $this->assertSame(TaskStatus::Progress, $restored->status);
        $this->assertTrue($restored->start_date->equalTo(now()->addDay()));
        $this->assertTrue($restored->deadline->equalTo(now()->addDays(5)));
        $this->assertNull($restored->deleted_by);
    }

    public function test_restore_does_not_change_finished_status(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Done, 'deadline' => now()->subDays(5)]);
        $task->delete();

        $this->authAsAdmin()->post(route('admin.tasks.restore', ['task' => $task->id]), [
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_restore_rejects_past_deadline(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(5)]);
        $task->delete();

        $this->authAsAdmin()
            ->from(route('admin.tasks.restore.form', ['task' => $task->id]))
            ->post(route('admin.tasks.restore', ['task' => $task->id]), [
                'start_date' => now()->subDay()->format('Y-m-d H:i:s'),
                'deadline' => now()->subDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors(['start_date', 'deadline']);

        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);
    }

    public function test_restore_requires_dates(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);
        $task->delete();

        $this->authAsAdmin()
            ->post(route('admin.tasks.restore', ['task' => $task->id]), [])
            ->assertSessionHasErrors(['start_date', 'deadline']);

        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);
    }

    public function test_restore_rejects_deadline_before_start(): void
    {
        $task = $this->task(['status' => TaskStatus::Late]);
        $task->delete();

        $this->authAsAdmin()
            ->post(route('admin.tasks.restore', ['task' => $task->id]), [
                'start_date' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('deadline');
    }

    public function test_restoring_active_task_returns_404(): void
    {
        $task = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $this->authAsAdmin()
            ->get(route('admin.tasks.restore.form', ['task' => $task->id]))
            ->assertNotFound();
    }

    public function test_restore_form_suggests_future_dates(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $task = $this->task(['status' => TaskStatus::Late, 'deadline' => now()->subDays(5)]);
        $task->delete();

        $this->authAsAdmin()
            ->get(route('admin.tasks.restore.form', ['task' => $task->id]))
            ->assertOk()
            ->assertSee($task->task)
            ->assertSee(now()->format('Y-m-d\TH:i'), false);
    }

    public function test_manual_delete_records_the_acting_admin(): void
    {
        Carbon::setTestNow('2026-10-10 09:00:00');

        $admin = $this->admin();
        $task = $this->task(['status' => TaskStatus::Progress, 'deadline' => now()->addDays(2)]);

        $this->actingAs($admin, 'web')
            ->delete(route('admin.tasks.destroy', $task))
            ->assertRedirect(route('admin.tasks.index'));

        $this->assertSame($admin->id, $task->fresh()->deleted_by);
    }
}
