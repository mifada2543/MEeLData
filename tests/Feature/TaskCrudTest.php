<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'trainee_id' => null,
            'task' => 'Maintenance Kabel',
            'desc' => 'Perbaikan kabel jaringan.',
            'start_date' => now()->addDays(0)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'status' => 'progress',
        ], $overrides);
    }

    public function test_admin_can_create_task_for_trainee(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.tasks.store'), $this->validPayload([
                'trainee_id' => $trainee->id,
            ]));

        $response->assertRedirect(route('admin.tasks.index'));
        $this->assertDatabaseHas('trainee_tasks', [
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
            'status' => 'progress',
        ]);
    }

    public function test_task_id_is_generated_as_uuid(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();

        $this->actingAs($admin, 'web')->post(route('admin.tasks.store'), $this->validPayload([
            'trainee_id' => $trainee->id,
        ]));

        $task = TraineeTask::firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $task->id,
        );
    }

    public function test_create_requires_trainee_for_admin(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), $this->validPayload(['trainee_id' => null]));

        $response->assertSessionHasErrors('trainee_id');
    }

    public function test_admin_account_cannot_be_assigned_as_trainee(): void
    {
        // Akun admin berada di tabel yang sama dengan trainee, jadi harus
        // ditolak oleh validasi server (bukan hanya disembunyikan di UI).
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'web')
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), $this->validPayload([
                'trainee_id' => $admin->id,
            ]));

        $response->assertSessionHasErrors('trainee_id');
        $this->assertDatabaseMissing('trainee_tasks', ['trainee_id' => $admin->id]);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), $this->validPayload([
                'trainee_id' => $this->trainee()->id,
                'task' => '',
                'start_date' => null,
                'deadline' => null,
            ]));

        $response->assertSessionHasErrors(['task', 'start_date', 'deadline']);
    }

    public function test_deadline_cannot_be_before_start_date(): void
    {
        $response = $this->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), $this->validPayload([
                'trainee_id' => $this->trainee()->id,
                'start_date' => now()->addDays(9)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(0)->format('Y-m-d H:i:s'),
            ]));

        $response->assertSessionHasErrors('deadline');
    }

    public function test_description_may_be_empty(): void
    {
        $trainee = $this->trainee();

        $this->authAsAdmin()->post(route('admin.tasks.store'), $this->validPayload([
            'trainee_id' => $trainee->id,
            'desc' => null,
        ]));

        $this->assertDatabaseHas('trainee_tasks', ['task' => 'Maintenance Kabel', 'desc' => null]);
    }

    public function test_status_defaults_to_progress_when_omitted(): void
    {
        $trainee = $this->trainee();

        $this->authAsAdmin()->post(route('admin.tasks.store'), $this->validPayload([
            'trainee_id' => $trainee->id,
            'status' => null,
        ]));

        $this->assertDatabaseHas('trainee_tasks', ['status' => TaskStatus::Progress->value]);
    }

    public function test_admin_can_view_all_tasks(): void
    {
        $this->task();
        $this->task();

        $response = $this->authAsAdmin()->get(route('admin.tasks.index'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('tasks'));
    }

    public function test_admin_can_update_task(): void
    {
        $task = $this->task(['task' => 'Old Task']);

        $response = $this->authAsAdmin()->put(route('admin.tasks.update', $task), [
            'task' => 'Updated Task',
            'desc' => 'Updated description.',
            'start_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('admin.tasks.index'));
        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'task' => 'Updated Task',
        ]);
    }

    public function test_admin_can_delete_task_using_soft_delete(): void
    {
        $task = $this->task();

        $response = $this->authAsAdmin()->delete(route('admin.tasks.destroy', $task));

        $response->assertRedirect(route('admin.tasks.index'));
        $this->assertSoftDeleted('trainee_tasks', ['id' => $task->id]);

        // Row tetap ada di database, hanya disembunyikan dari query normal.
        $this->assertDatabaseHas('trainee_tasks', ['id' => $task->id]);
    }

    public function test_deleted_task_is_not_visible_in_list(): void
    {
        $task = $this->task();
        $this->task();

        $task->delete();

        $response = $this->authAsAdmin()->get(route('admin.tasks.index'));

        $this->assertCount(1, $response->viewData('tasks'));
    }

    public function test_trainee_can_create_task_for_self(): void
    {
        $trainee = $this->trainee();

        $response = $this->actingAs($trainee, 'web')
            ->post(route('tasks.store'), $this->validPayload());

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('trainee_tasks', [
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
        ]);
    }

    public function test_trainee_can_update_own_task_metadata(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id, 'task' => 'Old Task']);

        $response = $this->actingAs($trainee, 'web')->put(route('tasks.update', $task), [
            'task' => 'Updated by trainee',
            'desc' => 'Updated.',
            'start_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('trainee_tasks', [
            'id' => $task->id,
            'task' => 'Updated by trainee',
        ]);
    }

    public function test_trainee_list_only_contains_own_tasks(): void
    {
        $trainee = $this->trainee();
        $this->task(['trainee_id' => $trainee->id]);
        $this->task();
        $this->task();

        $response = $this->actingAs($trainee, 'web')->get(route('tasks.index'));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('tasks'));
        $this->assertSame($trainee->id, $response->viewData('tasks')->first()->trainee_id);
    }

    public function test_task_pagination_defaults_to_25_per_page(): void
    {
        $trainee = $this->trainee();
        TraineeTask::factory()->count(30)->create(['trainee_id' => $trainee->id]);

        $response = $this->actingAs($trainee, 'web')->get(route('tasks.index'));

        $tasks = $response->viewData('tasks');

        $this->assertSame(25, $tasks->perPage());
        $this->assertSame(30, $tasks->total());
        $this->assertCount(25, $tasks->items());
    }

    public function test_edit_page_is_reachable_for_own_task(): void
    {
        $trainee = $this->trainee();
        $task = $this->task(['trainee_id' => $trainee->id]);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.edit', $task))
            ->assertOk();
    }
}
