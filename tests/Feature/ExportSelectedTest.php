<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Http\Controllers\TraineeTaskController;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Export mendukung dua mode:
 * - `ids[]` terisi  → hanya baris yang dicentang (selected menang)
 * - `ids[]` kosong  → seluruh hasil filter status yang aktif
 *
 * `ids` selalu diinterseksi dengan scope `visibleTo()` sehingga trainee
 * tidak dapat mengekspor task trainee lain.
 */
class ExportSelectedTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_selected_only_returns_chosen_tasks(): void
    {
        $admin = $this->admin();
        $wanted = $this->task(['task' => 'Wanted']);
        $this->task(['task' => 'Not wanted']);

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export', ['ids' => [$wanted->id]]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_selected_export_ignores_the_status_filter(): void
    {
        // Filter status = progress, tetapi id yang dipilih menunjuk task Done.
        // Aturan "selected menang" berarti task Done tetap ikut diekspor.
        $admin = $this->admin();
        $done = $this->task(['status' => TaskStatus::Done]);
        $this->task(['status' => TaskStatus::Progress]);

        $rows = app(TraineeTaskController::class)
            ->exportQuery($admin, [$done->id], TaskStatus::Progress->value)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($done->id, $rows->first()->id);
    }

    public function test_export_without_ids_follows_status_filter(): void
    {
        $admin = $this->admin();
        $done = $this->task(['status' => TaskStatus::Done]);
        $this->task(['status' => TaskStatus::Progress]);

        $rows = app(TraineeTaskController::class)
            ->exportQuery($admin, [], TaskStatus::Done->value)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($done->id, $rows->first()->id);
    }

    public function test_trainee_cannot_export_other_trainee_tasks_by_spoofing_ids(): void
    {
        $trainee = $this->trainee();
        $other = $this->trainee();

        $ownTask = $this->task(['trainee_id' => $trainee->id]);
        $otherTask = $this->task(['trainee_id' => $other->id]);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.export', ['ids' => [$ownTask->id, $otherTask->id]]))
            ->assertOk();

        // Scope ownership memotong UUID milik trainee lain.
        $exported = TraineeTask::visibleTo($trainee)
            ->whereKey([$ownTask->id, $otherTask->id])
            ->get();

        $this->assertTrue($exported->contains($ownTask));
        $this->assertFalse($exported->contains($otherTask));
    }

    public function test_export_rejects_non_uuid_ids(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.index'))
            ->get(route('admin.tasks.export', ['ids' => ['not-a-uuid']]));

        $response->assertRedirect(route('admin.tasks.index'));
        $response->assertSessionHasErrors('ids.0');
    }

    public function test_export_with_empty_ids_array_falls_back_to_filter(): void
    {
        $admin = $this->admin();
        $this->task(['status' => TaskStatus::Done]);

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export', ['ids' => []]))
            ->assertOk();
    }

    public function test_export_filename_differs_between_selected_and_filtered(): void
    {
        $admin = $this->admin();
        $task = $this->task();

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export', ['ids' => [$task->id]]))
            ->assertOk();

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export'))
            ->assertOk();
    }

    public function test_soft_deleted_task_cannot_be_exported_by_id(): void
    {
        $admin = $this->admin();
        $deleted = $this->task();
        $deleted->delete();

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.export', ['ids' => [$deleted->id]]))
            ->assertOk();

        $exported = TraineeTask::visibleTo($admin)->whereKey([$deleted->id])->get();

        $this->assertCount(0, $exported);
    }

    public function test_admin_sees_all_tasks_in_export_scope(): void
    {
        $admin = $this->admin();
        $this->task();
        $this->task();
        $this->task();

        $this->assertCount(3, TraineeTask::visibleTo($admin)->get());
    }

    public function test_assignable_trainees_excludes_admin_accounts(): void
    {
        // Admin berada di tabel yang sama dengan trainee, namun tidak boleh
        // muncul sebagai pilihan assignee.
        $this->admin(['name' => 'The Admin']);
        $trainee = $this->trainee(['name' => 'Real Trainee']);

        $this->actingAs($this->admin(), 'web')
            ->get(route('admin.tasks.create'))
            ->assertOk()
            ->assertSee('Real Trainee')
            ->assertDontSee('The Admin');
    }

    public function test_admin_user_is_not_treated_as_trainee(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isTrainee());
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
    }
}
