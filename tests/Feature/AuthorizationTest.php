<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainee_cannot_read_other_trainee_task(): void
    {
        $other = $this->trainee();
        $task = $this->task(['trainee_id' => $other->id]);

        $this->authAsTrainee()
            ->get(route('tasks.edit', $task))
            ->assertForbidden();
    }

    public function test_trainee_cannot_update_other_trainee_task(): void
    {
        $other = $this->trainee();
        $task = $this->task(['trainee_id' => $other->id, 'task' => 'Owned by other']);

        $response = $this->authAsTrainee()->put(route('tasks.update', $task), [
            'task' => 'Hijacked',
            'desc' => 'desc',
            'start_date' => '2026-10-01 09:00:00',
            'deadline' => '2026-10-05 17:00:00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'task' => 'Owned by other',
        ]);
    }

    public function test_trainee_cannot_update_status_of_other_trainee_task(): void
    {
        $other = $this->trainee();
        $task = $this->task(['trainee_id' => $other->id, 'status' => TaskStatus::Progress]);

        $this->authAsTrainee()
            ->patch(route('tasks.status', $task), ['status' => 'done'])
            ->assertForbidden();

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Progress->value,
        ]);
    }

    public function test_trainee_cannot_manipulate_trainee_id_on_create(): void
    {
        $attacker = $this->trainee();
        $victim = $this->trainee();

        $this->actingAs($attacker, 'web')->post(route('tasks.store'), [
            // Nilai ini harus diabaikan; ownership mengikuti attacker.
            'trainee_id' => $victim->id,
            'task' => 'Task for other trainee',
            'desc' => 'desc',
            'start_date' => '2026-10-01 09:00:00',
            'deadline' => '2026-10-05 17:00:00',
        ]);

        $this->assertDatabaseHas('trainee_tasks', [
            'task' => 'Task for other trainee',
            'trainee_id' => $attacker->id,
        ]);
        $this->assertDatabaseMissing('trainee_tasks', [
            'task' => 'Task for other trainee',
            'trainee_id' => $victim->id,
        ]);
    }

    public function test_trainee_cannot_change_ownership_of_own_task(): void
    {
        $attacker = $this->trainee();
        $victim = $this->trainee();
        $task = $this->task(['trainee_id' => $attacker->id]);

        $this->actingAs($attacker, 'web')->put(route('tasks.update', $task), [
            'trainee_id' => $victim->id,
            'task' => 'Moved task',
            'desc' => 'desc',
            'start_date' => '2026-10-01 09:00:00',
            'deadline' => '2026-10-05 17:00:00',
        ]);

        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'trainee_id' => $attacker->id,
        ]);
    }

    public function test_trainee_has_no_delete_route(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id]);

        // Tidak ada route DELETE pada area trainee sama sekali. Method yang
        // tidak terdaftar menghasilkan 405, bukan 404, karena URI-nya ada
        // sebagai route GET/PUT/PATCH.
        $this->authAsTrainee()
            ->delete(route('tasks.update', $task))
            ->assertStatus(405);

        $this->assertDatabaseHas('trainee_tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_trainee_cannot_bulk_delete(): void
    {
        $trainee = $this->trainee();
        $tasks = TraineeTask::factory()->count(3)->create(['trainee_id' => $trainee->id]);

        $this->authAsTrainee()
            ->post(route('admin.tasks.bulk-delete'), ['ids' => $tasks->pluck('id')->all()])
            ->assertForbidden();

        $this->assertSame(3, TraineeTask::count());
    }

    public function test_trainee_cannot_access_admin_routes(): void
    {
        $this->authAsTrainee()
            ->get(route('admin.tasks.index'))
            ->assertForbidden();

        $this->authAsTrainee()
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_trainee_cannot_import(): void
    {
        $this->authAsTrainee()
            ->post(route('admin.tasks.import'))
            ->assertForbidden();

        $this->authAsTrainee()
            ->get(route('admin.tasks.import.template'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_pages(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
        $this->get(route('admin.tasks.index'))->assertRedirect(route('admin.login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_any_task(): void
    {
        $task = $this->task();

        $this->authAsAdmin()->get(route('admin.tasks.edit', $task))->assertOk();
        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk();
    }

    public function test_soft_deleted_task_returns_404_for_admin(): void
    {
        $task = $this->task();
        $task->delete();

        $this->authAsAdmin()
            ->get(route('admin.tasks.edit', $task))
            ->assertNotFound();
    }

    public function test_soft_deleted_task_returns_403_for_other_trainee(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id]);
        $task->delete();

        // Task milik orang lain yang sudah dihapus: 403 (ditemukan, bukan milik kita).
        $this->actingAs($this->trainee(), 'web')
            ->get(route('tasks.edit', $task))
            ->assertForbidden();
    }

    public function test_non_existent_task_returns_404(): void
    {
        $this->authAsAdmin()
            ->get(route('admin.tasks.edit', '01b0a9c0-0000-4000-8000-000000000000'))
            ->assertNotFound();
    }

    public function test_visible_to_scope_limits_data_at_query_level(): void
    {
        $trainee = $this->trainee();
        $ownTask = $this->task(['trainee_id' => $trainee->id]);
        $otherTask = $this->task();

        $this->actingAs($trainee, 'web');
        auth()->shouldUse('web');

        $visible = TraineeTask::visibleTo(auth()->user())->pluck('id');

        $this->assertTrue($visible->contains($ownTask->id));
        $this->assertFalse($visible->contains($otherTask->id));
    }

    public function test_admin_visible_to_scope_returns_all_tasks(): void
    {
        $admin = $this->admin();
        $this->task();
        $this->task();

        $this->assertCount(2, TraineeTask::visibleTo($admin)->get());
    }

    public function test_gate_admin_only_true_for_admin(): void
    {
        $this->assertTrue($this->admin()->can('admin'));
        $this->assertFalse(User::factory()->create()->can('admin'));
    }
}
